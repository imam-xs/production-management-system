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

  
    // the log the RabbitMQ consumer writes
    public function eventLog(int $perPage = 15): LengthAwarePaginator
    {
        return ProductionEventLog::query()
            ->with('productionOrder')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    
     //plan a production run which stage, how much, of what — without touching inventory
     //inventory is only ever moved by execute()

    public function createOrder(Item $outputItem, string $plannedQuantity, ?User $createdBy = null): ProductionOrder
    {
        $stage = ProductionStage::forOutputType($outputItem->type);

        if ($stage === null) {
            throw InvalidProductionStageException::itemIsNotProduced($outputItem);
        }

        // output only — retired items stay consumable as inputs

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
            'order_number'     => $this->orderNumbers->generate(now()),
            'stage'            => $stage,
            'output_item_id'   => $outputItem->id,
            'planned_quantity' => $plannedQuantity,
            'status'           => ProductionOrderStatus::Pending,
            'created_by'       => $createdBy?->id,
        ]);
    }

   
     //execute a pending order: consume its recipe's inputs and produce one output batch
   
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

    //lock the order row and confirm it is still pending, the caller's copy may be stale

    private function lockPendingOrder(ProductionOrder $order): ProductionOrder
    {
        $locked = $this->orders->lockById($order->id);

        if ($locked === null) {
            throw (new ModelNotFoundException)->setModel(ProductionOrder::class, [$order->id]);
        }

        if (! $locked->status->canBeExecuted()) {
            throw ProductionOrderNotPendingException::forOrder($locked);
        }

        return $locked;
    }


    //plan which batches will supply each input
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

    //consume the allocated inputs
    private function consumeInputs(ProductionOrder $order, array $allocations): void
    {
        foreach ($allocations as $allocation) {
            $this->consumeAllocation($order, $allocation['item'], $allocation['plan']);
        }
    }

    ///create the output batch, add it to stock and record the matching ledger row

    private function produceOutput(ProductionOrder $order, Item $outputItem): Batch
    {
        $quantity = (string) $order->planned_quantity;

        $outputBatch = $this->batchFactory->make(
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

  //deduct one input's planned quantity from its batches, recording a consumption edge and a ledger row for each

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
