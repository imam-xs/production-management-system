<?php

namespace App\Services;

use App\Enums\BatchOrigin;
use App\Enums\MovementType;
use App\Enums\ProductionOrderStatus;
use App\Enums\ProductionStage;
use App\Events\ProductionCompleted;
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
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductionService
{
    private const MAX_ORDER_NUMBER_ATTEMPTS = 5;

    public function __construct(
        private readonly ItemRepositoryInterface $items,
        private readonly BatchRepositoryInterface $batches,
        private readonly BillOfMaterialRepositoryInterface $boms,
        private readonly ProductionOrderRepositoryInterface $orders,
        private readonly InventoryRepositoryInterface $inventory,
        private readonly InventoryAllocator $allocator,
        private readonly BatchService $batchService,
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

    public function findOrFail(int $id): ProductionOrder
    {
        return $this->orders->findByIdOrFail($id);
    }

    /**
     * The log the RabbitMQ consumer writes.
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

    // plan a production run which stage, how much, of what — without touching inventory
    // inventory is only ever moved by execute()

    public function createOrder(Item $outputItem, string $plannedQuantity, ?User $createdBy = null): ProductionOrder
    {
        $stage = ProductionStage::forOutputType($outputItem->type);

        if ($stage === null) {
            throw ValidationException::withMessages([
                'output_item_id' => sprintf('%s (%s) is a raw material and cannot be the output of a production run.', $outputItem->name, $outputItem->sku),
            ]);
        }

        // output only — retired items stay consumable as inputs

        if (! $outputItem->is_active) {
            throw ValidationException::withMessages([
                'output_item_id' => sprintf('%s (%s) is retired and cannot be produced. Mark it active first.', $outputItem->name, $outputItem->sku),
            ]);
        }

        if (bccomp($plannedQuantity, '0', 4) <= 0) {
            throw ValidationException::withMessages([
                'planned_quantity' => 'Planned quantity must be greater than zero.',
            ]);
        }

        if (! $this->boms->hasRecipe($outputItem)) {
            throw ValidationException::withMessages([
                'output_item_id' => sprintf('%s (%s) has no bill-of-materials recipe, so production cannot be planned.', $outputItem->name, $outputItem->sku),
            ]);
        }

        // Same retry as BatchService: the sequence is read then written, so two
        // requests in the same second can compute the same number. The unique
        // index rejects the loser, and it takes the next one.
        for ($attempt = 1; ; $attempt++) {
            try {
                return $this->orders->create([
                    'order_number' => $this->nextOrderNumber(now()),
                    'stage' => $stage,
                    'output_item_id' => $outputItem->id,
                    'planned_quantity' => $plannedQuantity,
                    'status' => ProductionOrderStatus::Pending,
                    'created_by' => $createdBy?->id,
                ]);
            } catch (QueryException $e) {
                if ($attempt >= self::MAX_ORDER_NUMBER_ATTEMPTS || ! $this->isDuplicateOrderNumber($e)) {
                    throw $e;
                }
            }
        }
    }

    private function isDuplicateOrderNumber(QueryException $e): bool
    {
        $isDuplicateEntry = ($e->errorInfo[1] ?? null) === 1062;

        return $isDuplicateEntry && str_contains($e->getMessage(), 'production_orders_order_number_unique');
    }

    // PO-Ymd-sequence, e.g. PO-20260730-0001 — a candidate, not a guarantee:
    // the unique index on order_number is what actually enforces uniqueness
    private function nextOrderNumber(DateTimeInterface $createdAt): string
    {
        $sequence = $this->orders->countCreatedOn($createdAt) + 1;

        return sprintf('PO-%s-%04d', $createdAt->format('Ymd'), $sequence);
    }

    // execute a pending order: consume its recipe's inputs and produce one output batch

    public function execute(ProductionOrder $order): ProductionOrder
    {
        return DB::transaction(function () use ($order): ProductionOrder {
            $locked = $this->lockPendingOrder($order);
            $outputItem = $this->items->findByIdOrFail($locked->output_item_id);

            $allocations = $this->allocateInputs($locked, $outputItem);
            $this->consumeInputs($locked, $allocations);
            $this->produceOutput($locked, $outputItem);

            $completed = $this->orders->markCompleted($locked, (string) $locked->planned_quantity);

            // ShouldDispatchAfterCommit = the message reaches RabbitMQ only once this transaction commits
            ProductionCompleted::dispatch($completed);

            return $completed;
        }, attempts: 3);
    }

    // lock the order row and confirm it is still pending, the caller's copy may be stale

    private function lockPendingOrder(ProductionOrder $order): ProductionOrder
    {
        $locked = $this->orders->lockById($order->id);

        if ($locked === null) {
            throw (new ModelNotFoundException)->setModel(ProductionOrder::class, [$order->id]);
        }

        if (! $locked->status->canBeExecuted()) {
            abort(409, sprintf('Production order %s cannot be modified because it is already %s.', $locked->order_number, $locked->status->value));
        }

        return $locked;
    }

    /**
     * Plan which batches will supply each input — nothing is written yet.
     *
     * @return list<array{item: Item, plan: list<array{batch: Batch, quantity: string}>}>
     */
    private function allocateInputs(ProductionOrder $order, Item $outputItem): array
    {
        $allocations = [];

        foreach ($this->boms->recipeFor($outputItem) as $line) {
            $required = bcmul((string) $line->quantity_per_unit, (string) $order->planned_quantity, 4);

            $allocations[] = [
                'item' => $line->inputItem,
                'plan' => $this->allocator->allocate($line->inputItem, $required),
            ];
        }

        return $allocations;
    }

    /**
     * Consume the allocated inputs.
     *
     * @param  list<array{item: Item, plan: list<array{batch: Batch, quantity: string}>}>  $allocations
     */
    private function consumeInputs(ProductionOrder $order, array $allocations): void
    {
        foreach ($allocations as $allocation) {
            $this->consumeAllocation($order, $allocation['item'], $allocation['plan']);
        }
    }

    // /create the output batch, add it to stock and record the matching ledger row

    private function produceOutput(ProductionOrder $order, Item $outputItem): Batch
    {
        $quantity = (string) $order->planned_quantity;

        $outputBatch = $this->batchService->make(
            $outputItem,
            $quantity,
            BatchOrigin::Production,
            $order,
            now(),
        );

        $stock = $this->inventory->lockStockFor($outputItem);
        $balance = $this->inventory->adjustStock($stock, $quantity);

        $this->inventory->recordMovement(
            $outputItem,
            $outputBatch,
            MovementType::ProductionOutput,
            $quantity,
            $balance,
            reference: $order,
        );

        return $outputBatch;
    }

    /**
     * Deduct one input's planned quantity from its batches, recording a
     * consumption edge and a ledger row for each.
     *
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
