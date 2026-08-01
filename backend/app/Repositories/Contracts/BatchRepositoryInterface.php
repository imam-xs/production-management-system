<?php

namespace App\Repositories\Contracts;

use App\Enums\BatchOrigin;
use App\Enums\ItemType;
use App\Models\Batch;
use App\Models\Item;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Data access for batches — including the pessimistic lock that makes
 * concurrent production safe.
 */
interface BatchRepositoryInterface
{
    /**
     * @param  bool  $availableOnly  Only batches with stock left — the FIFO-relevant view.
     * @return LengthAwarePaginator<int, Batch>
     */
    public function paginate(
        ?string $search = null,
        ?int $itemId = null,
        ?ItemType $itemType = null,
        ?BatchOrigin $origin = null,
        bool $availableOnly = false,
        int $perPage = 15,
    ): LengthAwarePaginator;

    public function findById(int $id): ?Batch;

    /**
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findByIdOrFail(int $id): Batch;

    public function findByNumber(string $batchNumber): ?Batch;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Batch;

    /**
     * Batches of an item that still hold stock, oldest first, **locked for
     * update**.
     *
     * The `SELECT ... FOR UPDATE` is the core of the concurrency guarantee: two
     * simultaneous production runs against the same item serialise here, so the
     * second one sees the first one's deductions instead of reading stale stock
     * and overselling. Must be called inside a transaction.
     *
     * @return Collection<int, Batch>
     */
    public function lockAvailableFifo(int $itemId): Collection;

    /**
     * Reduce a batch's remaining quantity. Assumes the batch is already locked.
     */
    public function decrementRemaining(Batch $batch, string $quantity): void;

    /**
     * Whether any batch of this item still holds stock — the guard that stops an
     * item being deleted while inventory exists.
     */
    public function hasRemainingStock(Item $item): bool;

    /**
     * Whether the item has ever been batched, regardless of what is left.
     *
     * Stronger than hasRemainingStock and the one deletion actually needs: a
     * fully consumed batch still anchors a traceability chain, so removing the
     * item it names would break every trace that passes through it.
     */
    public function hasAnyBatch(Item $item): bool;

    /**
     * Batches of one item type produced on a given day, used to allocate the
     * next batch-number sequence. Scoped by type (not just date) so RM/SF/FG
     * numbering each start their own daily sequence.
     */
    public function countProducedOn(\DateTimeInterface $date, ?ItemType $type = null): int;
}
