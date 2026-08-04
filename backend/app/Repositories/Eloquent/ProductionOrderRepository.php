<?php

namespace App\Repositories\Eloquent;

use App\Enums\ProductionOrderStatus;
use App\Enums\ProductionStage;
use App\Models\BatchModel;
use App\Models\ProductionConsumptionModel;
use App\Models\ProductionOrderModel;
use App\Repositories\Contracts\ProductionOrderRepositoryInterface;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ProductionOrderRepository implements ProductionOrderRepositoryInterface
{
    public function paginate(
        ?string $search = null,
        ?ProductionStage $stage = null,
        ?ProductionOrderStatus $status = null,
        ?int $outputItemId = null,
        int $perPage = 15,
    ): LengthAwarePaginator {
        $query = ProductionOrderModel::query()->with(['outputItem.unit', 'outputBatch', 'creator']);

        if ($stage instanceof ProductionStage) {
            $query->where('stage', $stage);
        }

        if ($status instanceof ProductionOrderStatus) {
            $query->where('status', $status);
        }

        if ($outputItemId !== null) {
            $query->where('output_item_id', $outputItemId);
        }

        if ($search !== null && $search !== '') {
            $query->where('order_number', 'like', "%{$search}%");
        }

        // newest first; id breaks ties within the same second
        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findByIdOrFail(int $id): ProductionOrderModel
    {
        return ProductionOrderModel::query()
            ->with(['outputItem.unit', 'outputBatch', 'creator', 'consumptions.inputBatch.item'])
            ->findOrFail($id);
    }

    public function lockById(int $id): ?ProductionOrderModel
    {
        // No eager loading: a locking read should touch exactly the row it locks.
        return ProductionOrderModel::query()->lockForUpdate()->find($id);
    }

    public function create(array $attributes): ProductionOrderModel
    {
        return ProductionOrderModel::query()->create($attributes);
    }

    public function markCompleted(ProductionOrderModel $order, string $producedQuantity): ProductionOrderModel
    {
        $order->fill([
            'status' => ProductionOrderStatus::Completed,
            'produced_quantity' => $producedQuantity,
            'completed_at' => now(),
        ])->save();

        return $order->refresh();
    }

    public function recordConsumption(
        ProductionOrderModel $order,
        BatchModel $inputBatch,
        string $quantity,
    ): ProductionConsumptionModel {
        return ProductionConsumptionModel::query()->create([
            'production_order_id' => $order->id,
            'input_batch_id' => $inputBatch->id,
            'quantity_consumed' => $quantity,
        ]);
    }

    public function consumptionsWithBatches(ProductionOrderModel $order): Collection
    {
        return ProductionConsumptionModel::query()
            ->with(['inputBatch.item.unit', 'inputBatch.productionOrder'])
            ->where('production_order_id', $order->id)
            ->orderBy('id')
            ->get();
    }

    public function consumptionsOfBatch(BatchModel $batch): Collection
    {
        return ProductionConsumptionModel::query()
            ->with(['productionOrder.outputItem.unit', 'productionOrder.outputBatch'])
            ->where('input_batch_id', $batch->id)
            ->orderBy('id')
            ->get();
    }

    public function countCreatedOn(DateTimeInterface $date): int
    {
        return ProductionOrderModel::query()
            ->whereDate('created_at', $date->format('Y-m-d'))
            ->count();
    }
}
