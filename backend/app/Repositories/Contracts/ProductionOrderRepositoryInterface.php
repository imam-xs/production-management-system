<?php

namespace App\Repositories\Contracts;

use App\Models\BatchModel;
use App\Models\ProductionConsumptionModel;
use App\Models\ProductionOrderModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ProductionOrderRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findByIdOrFail(int $id): ProductionOrderModel;

    public function lockById(int $id): ?ProductionOrderModel;

    public function create(array $attributes): ProductionOrderModel;

    public function markCompleted(ProductionOrderModel $order, string $producedQuantity): ProductionOrderModel;

    public function recordConsumption(
        ProductionOrderModel $order,
        BatchModel $inputBatch,
        string $quantity,
    ): ProductionConsumptionModel;

    public function consumptionsWithBatches(ProductionOrderModel $order): Collection;

    public function consumptionsOfBatch(BatchModel $batch): Collection;

    public function countCreatedOn(\DateTimeInterface $date): int;
}
