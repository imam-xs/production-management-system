<?php

namespace App\Repositories\Contracts;

use App\Enums\ProductionOrderStatus;
use App\Enums\ProductionStage;
use App\Models\Batch;
use App\Models\ProductionConsumption;
use App\Models\ProductionOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ProductionOrderRepositoryInterface
{
    /**
     * @return LengthAwarePaginator<int, ProductionOrder>
     */
    public function paginate(
        ?string $search = null,
        ?ProductionStage $stage = null,
        ?ProductionOrderStatus $status = null,
        ?int $outputItemId = null,
        int $perPage = 15,
    ): LengthAwarePaginator;

    /**
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findByIdOrFail(int $id): ProductionOrder;

    public function lockById(int $id): ?ProductionOrder;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): ProductionOrder;

    public function markCompleted(ProductionOrder $order, string $producedQuantity): ProductionOrder;

    public function recordConsumption(
        ProductionOrder $order,
        Batch $inputBatch,
        string $quantity,
    ): ProductionConsumption;

    /**
     * @return Collection<int, ProductionConsumption>
     */
    public function consumptionsWithBatches(ProductionOrder $order): Collection;

    /**
     * @return Collection<int, ProductionConsumption>
     */
    public function consumptionsOfBatch(Batch $batch): Collection;

    public function countCreatedOn(\DateTimeInterface $date): int;
}
