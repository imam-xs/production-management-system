<?php

namespace App\Repositories\Contracts;

use App\Enums\ProductionOrderStatus;
use App\Enums\ProductionStage;
use App\Models\Batch;
use App\Models\ProductionConsumption;
use App\Models\ProductionOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Data access for production orders and the consumption edges hanging off them.
 *
 * The consumption rows live here rather than in their own repository because
 * they have no independent lifecycle — they are only ever created as part of
 * executing an order, and only ever read as part of tracing one.
 */
interface ProductionOrderRepositoryInterface
{
    /**
     * Paginated production history, newest first.
     *
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

    /**
     * Fetch an order **locked for update**.
     *
     * Guarantees a repeated execute call on the same order serialises, so the
     * second attempt observes the completed status and is rejected rather than
     * deducting stock twice. Must be called inside a transaction.
     */
    public function lockById(int $id): ?ProductionOrder;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): ProductionOrder;

    public function markCompleted(ProductionOrder $order, string $producedQuantity): ProductionOrder;

    /**
     * Record that an order consumed part of an input batch — one traceability
     * edge.
     */
    public function recordConsumption(
        ProductionOrder $order,
        Batch $inputBatch,
        string $quantity,
    ): ProductionConsumption;

    /**
     * Consumption edges for an order, with the input batch and its item eager
     * loaded — one query per trace level instead of N.
     *
     * @return Collection<int, ProductionConsumption>
     */
    public function consumptionsWithBatches(ProductionOrder $order): Collection;

    /**
     * The reverse direction: runs that consumed a given batch, for answering
     * "where did this batch end up?".
     *
     * @return Collection<int, ProductionConsumption>
     */
    public function consumptionsOfBatch(Batch $batch): Collection;

    /**
     * Orders created on a given day, used to allocate the next order number.
     */
    public function countCreatedOn(\DateTimeInterface $date): int;
}
