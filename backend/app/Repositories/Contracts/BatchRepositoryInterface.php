<?php

namespace App\Repositories\Contracts;

use App\Enums\BatchOrigin;
use App\Enums\ItemType;
use App\Models\BatchModel;
use App\Models\ItemModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface BatchRepositoryInterface
{
    /**
     * available_only narrows to batches that still hold stock — the FIFO view.
     *
     * @param  array{search?: ?string, item_type?: ?ItemType, origin?: ?BatchOrigin, available_only?: bool}  $filters
     * @return LengthAwarePaginator<int, BatchModel>
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findByIdOrFail(int $id): BatchModel;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): BatchModel;

    /**
     * @return Collection<int, BatchModel>
     */
    public function lockAvailableFifo(int $itemId): Collection;

    // Reduce a batch's remaining quantity. Assumes the batch is already locked
    public function decrementRemaining(BatchModel $batch, string $quantity): void;

    public function hasRemainingStock(ItemModel $item): bool;

    // whether the item has ever been batched, regardless of what is left
    public function hasAnyBatch(ItemModel $item): bool;

    public function countProducedOn(\DateTimeInterface $date, ?ItemType $type = null): int;
}
