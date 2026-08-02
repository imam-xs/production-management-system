<?php

namespace App\Repositories\Eloquent;

use App\Enums\ProductionOrderStatus;
use App\Enums\ProductionStage;
use App\Models\Batch;
use App\Models\ProductionConsumption;
use App\Models\ProductionOrder;
use App\Repositories\Contracts\ProductionOrderRepositoryInterface;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<ProductionOrder>
 */
class ProductionOrderRepository extends BaseRepository implements ProductionOrderRepositoryInterface
{
    protected function model(): ProductionOrder
    {
        return new ProductionOrder;
    }

    public function paginate(
        ?string $search = null,
        ?ProductionStage $stage = null,
        ?ProductionOrderStatus $status = null,
        ?int $outputItemId = null,
        int $perPage = 15,
    ): LengthAwarePaginator {
        return $this->query()
            ->with(['outputItem.unit', 'outputBatch', 'creator'])
            ->when($stage instanceof ProductionStage, fn (Builder $q): Builder => $q->where('stage', $stage))
            ->when($status instanceof ProductionOrderStatus, fn (Builder $q): Builder => $q->where('status', $status))
            ->when($outputItemId !== null, fn (Builder $q): Builder => $q->where('output_item_id', $outputItemId))
            ->when(
                $search !== null && $search !== '',
                fn (Builder $q): Builder => $q->where('order_number', 'like', "%{$search}%"),
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findByIdOrFail(int $id): ProductionOrder
    {
        return $this->query()
            ->with(['outputItem.unit', 'outputBatch', 'creator', 'consumptions.inputBatch.item'])
            ->findOrFail($id);
    }

    public function lockById(int $id): ?ProductionOrder
    {
        // No eager loading: a locking read should touch exactly the row it locks.
        return $this->query()->lockForUpdate()->find($id);
    }

    public function create(array $attributes): ProductionOrder
    {
        return $this->persist($attributes);
    }

    public function markCompleted(ProductionOrder $order, string $producedQuantity): ProductionOrder
    {
        return $this->applyUpdate($order, [
            'status' => ProductionOrderStatus::Completed,
            'produced_quantity' => $producedQuantity,
            'completed_at' => now(),
        ]);
    }

    public function recordConsumption(
        ProductionOrder $order,
        Batch $inputBatch,
        string $quantity,
    ): ProductionConsumption {
        return ProductionConsumption::query()->create([
            'production_order_id' => $order->id,
            'input_batch_id' => $inputBatch->id,
            'quantity_consumed' => $quantity,
        ]);
    }

    public function consumptionsWithBatches(ProductionOrder $order): Collection
    {
        return ProductionConsumption::query()
            ->with(['inputBatch.item.unit', 'inputBatch.productionOrder'])
            ->where('production_order_id', $order->id)
            ->orderBy('id')
            ->get();
    }

    public function consumptionsOfBatch(Batch $batch): Collection
    {
        return ProductionConsumption::query()
            ->with(['productionOrder.outputItem.unit', 'productionOrder.outputBatch'])
            ->where('input_batch_id', $batch->id)
            ->orderBy('id')
            ->get();
    }

    public function countCreatedOn(DateTimeInterface $date): int
    {
        return $this->query()
            ->whereDate('created_at', $date->format('Y-m-d'))
            ->count();
    }
}
