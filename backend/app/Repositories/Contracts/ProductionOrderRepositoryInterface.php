<?php

namespace App\Repositories\Contracts;

use App\Enums\ProductionOrderStatus;
use App\Enums\ProductionStage;
use App\Models\BatchModel;
use App\Models\ProductionConsumptionModel;
use App\Models\ProductionOrderModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ProductionOrderRepositoryInterface
{
    /**
     * @param  array{stage?: ?ProductionStage, status?: ?ProductionOrderStatus}  $filters
     * @return LengthAwarePaginator<int, ProductionOrderModel>
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findByIdOrFail(int $id): ProductionOrderModel;

    public function lockById(int $id): ?ProductionOrderModel;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): ProductionOrderModel;

    public function markCompleted(ProductionOrderModel $order, string $producedQuantity): ProductionOrderModel;

    public function recordConsumption(
        ProductionOrderModel $order,
        BatchModel $inputBatch,
        string $quantity,
    ): ProductionConsumptionModel;

    /**
     * @return Collection<int, ProductionConsumptionModel>
     */
    public function consumptionsWithBatches(ProductionOrderModel $order): Collection;

    /**
     * @return Collection<int, ProductionConsumptionModel>
     */
    public function consumptionsOfBatch(BatchModel $batch): Collection;

    public function countCreatedOn(\DateTimeInterface $date): int;
}
