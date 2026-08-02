<?php

namespace App\Services;

use App\Enums\BatchOrigin;
use App\Enums\MovementType;
use App\Enums\ProductionOrderStatus;
use App\Enums\ProductionStage;
use App\Events\ProductionCompleted;
use App\Exceptions\InsufficientInventoryException;
use App\Exceptions\InvalidProductionStageException;
use App\Exceptions\ItemRetiredException;
use App\Exceptions\ProductionOrderNotPendingException;
use App\Models\Batch;
use App\Models\Item;
use App\Models\ProductionEventLog;
use App\Models\ProductionOrder;
use App\Models\User;
use App\Repositories\Contracts\BatchRepositoryInterface;
use App\Repositories\Contracts\BillOfMaterialRepositoryInterface;
use App\Repositories\Contracts\InventoryRepositoryInterface;
use App\Repositories\Contracts\ItemRepositoryInterface;
use App\Repositories\Contracts\ProductionOrderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Plans and executes production runs.
 *
 * execute() is where the assignment's two hardest requirements meet: stock
 * must be deducted/added atomically, and production must be blocked outright
 * when input inventory is short. Both only hold if the check and the write
 * happen in the same locked transaction — which is why this deduction is
 * synchronous rather than left to the RabbitMQ consumer (see the design
 * rationale in TASKS.md's decisions table). The consumer's job, wired in
 * Phase 5, is the side effects: event log, notifications — never the stock
 * numbers themselves.
 */
class ProductionService
{
    public function __construct(
        private readonly ItemRepositoryInterface $items,
        private readonly BatchRepositoryInterface $batches,
        private readonly BillOfMaterialRepositoryInterface $boms,
        private readonly ProductionOrderRepositoryInterface $orders,
        private readonly InventoryRepositoryInterface $inventory,
        private readonly InventoryAllocator $allocator,
        private readonly BatchFactory $batchFactory,
        private readonly OrderNumberGenerator $orderNumbers,
    ) {}

    /**
     * @return LengthAwarePaginator<int, ProductionOrder>
     */
    public function list(
        ?string $search = null,
        ?ProductionStage $stage = null,
        ?ProductionOrderStatus $status = null,
        ?int $outputItemId = null,
        int $perPage = 15,
    ): LengthAwarePaginator {
        return $this->orders->paginate($search, $stage, $status, $outputItemId, $perPage);
    }

    /**
     * @throws ModelNotFoundException
     */
    public function findOrFail(int $id): ProductionOrder
    {
        return $this->orders->findByIdOrFail($id);
    }

    /**
     * The event log the RabbitMQ consumer writes — exposed so the asynchronous
     * path is observable rather than something a reviewer has to take on trust.
     *
     * Queried through the model directly rather than a repository: this table is
     * append-only with no query complexity, which is exactly the case the
     * "repository only where it earns its keep" rule carves out.
     *
     * @return LengthAwarePaginator<int, ProductionEventLog>
     */
    public function eventLog(int $perPage = 15): LengthAwarePaginator
    {
        return ProductionEventLog::query()
            ->with('productionOrder')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Plan a production run: which stage, how much, of what — without touching
     * inventory. Inventory is only ever moved by execute().
     *
     * @throws InvalidProductionStageException
     */
    public function createOrder(Item $outputItem, string $plannedQuantity, ?User $createdBy = null): ProductionOrder
    {
        $stage = ProductionStage::forOutputType($outputItem->type);

        if ($stage === null) {
            throw InvalidProductionStageException::itemIsNotProduced($outputItem);
        }

        // Retired products leave circulation for new work. Enforced here rather
        // than only by hiding them from the UI's dropdown, so the rule holds for
        // any client — the interface filters as a convenience, not as the guard.
        //
        // Deliberately scoped to the *output*: existing stock of a retired input
        // must still be consumable, which is how a component is phased out
        // rather than stranded.
        if (! $outputItem->is_active) {
            throw ItemRetiredException::cannotProduce($outputItem);
        }

        if (bccomp($plannedQuantity, '0', 4) <= 0) {
            throw new InvalidArgumentException('Planned quantity must be greater than zero.');
        }

        if (! $this->boms->hasRecipe($outputItem)) {
            throw InvalidProductionStageException::missingRecipe($outputItem);
        }

        return $this->orders->create([
            'order_number' => $this->orderNumbers->generate(now()),
            'stage' => $stage,
            'output_item_id' => $outputItem->id,
            'planned_quantity' => $plannedQuantity,
            'status' => ProductionOrderStatus::Pending,
            'created_by' => $createdBy?->id,
        ]);
    }

    /**
     * Execute a pending order: consume its recipe's inputs and produce one
     * output batch.
     *
     * Wrapped with an automatic retry (Laravel's third `DB::transaction`
     * argument) rather than manual multi-item lock ordering: InnoDB detects a
     * genuine deadlock between two orders that lock overlapping items in
     * different orders and aborts one of them; Laravel re-runs this closure
     * from scratch when that happens, which is safe because nothing commits
     * until the very end.
     *
     * @throws ProductionOrderNotPendingException
     * @throws InsufficientInventoryException
     */
    public function execute(ProductionOrder $order): ProductionOrder
    {
        return DB::transaction(function () use ($order): ProductionOrder {
            $locked = $this->orders->lockById($order->id);

            if ($locked === null) {
                throw (new ModelNotFoundException)->setModel(ProductionOrder::class, [$order->id]);
            }

            if (! $locked->status->canBeExecuted()) {
                throw ProductionOrderNotPendingException::forOrder($locked);
            }

            $outputItem = $this->items->findByIdOrFail($locked->output_item_id);

            // Ordered by input_item_id (see BillOfMaterialRepository::recipeFor)
            // so two orders sharing overlapping inputs always attempt their
            // locks in the same relative order — reduces how often the retry
            // above is actually needed, though it remains the real guarantee.
            $recipe = $this->boms->recipeFor($outputItem);

            // Allocate every input line before writing anything. If any line
            // is short, InsufficientInventoryException aborts here and the
            // transaction rolls back whole — no partial consumption.
            $allocations = [];
            foreach ($recipe as $line) {
                $required = bcmul((string) $line->quantity_per_unit, (string) $locked->planned_quantity, 4);
                $allocations[] = [
                    'item' => $line->inputItem,
                    'plan' => $this->allocator->allocate($line->inputItem, $required),
                ];
            }

            foreach ($allocations as $allocation) {
                $this->consumeAllocation($locked, $allocation['item'], $allocation['plan']);
            }

            $outputBatch = $this->batchFactory->make(
                $outputItem,
                (string) $locked->planned_quantity,
                BatchOrigin::Production,
                $locked,
                now(),
            );

            $outputStock = $this->inventory->lockStockFor($outputItem);
            $balance = $this->inventory->adjustStock($outputStock, (string) $locked->planned_quantity);
            $this->inventory->recordMovement(
                $outputItem,
                $outputBatch,
                MovementType::ProductionOutput,
                (string) $locked->planned_quantity,
                $balance,
                reference: $locked,
            );

            $completed = $this->orders->markCompleted($locked, (string) $locked->planned_quantity);

            // ProductionCompleted implements ShouldDispatchAfterCommit, so this
            // call queues the dispatch rather than firing it now — the message
            // only reaches RabbitMQ once this transaction has committed. That
            // matters because this closure can be retried on deadlock: an
            // event published from inside it could describe an order that then
            // got rolled back.
            ProductionCompleted::dispatch($completed);

            return $completed;
        }, attempts: 3);
    }

    /**
     * @param  list<array{batch: Batch, quantity: string}>  $plan
     */
    private function consumeAllocation(ProductionOrder $order, Item $inputItem, array $plan): void
    {
        $stock = $this->inventory->lockStockFor($inputItem);

        foreach ($plan as $entry) {
            $batch = $entry['batch'];
            $quantity = $entry['quantity'];

            $this->orders->recordConsumption($order, $batch, $quantity);

            // $batch was already locked (SELECT ... FOR UPDATE) by the
            // allocator, so decrementing it here needs no further lock.
            $this->batches->decrementRemaining($batch, $quantity);

            $signedQuantity = bcmul($quantity, '-1', 4);
            $balance = $this->inventory->adjustStock($stock, $signedQuantity);
            $this->inventory->recordMovement($inputItem, $batch, MovementType::ProductionInput, $signedQuantity, $balance, reference: $order);
        }
    }
}
