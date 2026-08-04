<?php

namespace App\Repositories\Eloquent;

use App\Enums\BatchOrigin;
use App\Enums\ItemType;
use App\Models\BatchModel;
use App\Models\ItemModel;
use App\Repositories\Contracts\BatchRepositoryInterface;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class BatchRepository implements BatchRepositoryInterface
{
    public function paginate(
        ?string $search = null,
        ?int $itemId = null,
        ?ItemType $itemType = null,
        ?BatchOrigin $origin = null,
        bool $availableOnly = false,
        int $perPage = 15,
    ): LengthAwarePaginator {
        $query = BatchModel::query()->with(['item.unit', 'productionOrder']);

        if ($itemId !== null) {
            $query->where('item_id', $itemId);
        }

        // type lives on the item, not the batch, so the filter goes through the relation
        if ($itemType instanceof ItemType) {
            $query->whereHas('item', fn (Builder $i): Builder => $i->where('type', $itemType));
        }

        if ($origin instanceof BatchOrigin) {
            $query->where('origin', $origin);
        }

        if ($availableOnly) {
            $query->where('quantity_remaining', '>', 0);
        }

        if ($search !== null && $search !== '') {
            $query->where('batch_number', 'like', "%{$search}%");
        }

        // newest first; id breaks ties so two batches made in the same second
        // always come back in the same order
        return $query
            ->orderByDesc('produced_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findByIdOrFail(int $id): BatchModel
    {
        return BatchModel::query()->with(['item.unit', 'productionOrder'])->findOrFail($id);
    }

    public function create(array $attributes): BatchModel
    {
        return BatchModel::query()->create($attributes);
    }

    public function lockAvailableFifo(int $itemId): Collection
    {
        return BatchModel::query()
            ->where('item_id', $itemId)
            ->where('quantity_remaining', '>', 0)
            // Oldest stock leaves first; id breaks ties deterministically so the
            // allocation plan is reproducible.
            ->orderBy('produced_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    public function decrementRemaining(BatchModel $batch, string $quantity): void
    {
        $batch->quantity_remaining = bcsub((string) $batch->quantity_remaining, $quantity, 4);
        $batch->save();
    }

    public function hasRemainingStock(ItemModel $item): bool
    {
        return BatchModel::query()
            ->where('item_id', $item->id)
            ->where('quantity_remaining', '>', 0)
            ->exists();
    }

    public function hasAnyBatch(ItemModel $item): bool
    {
        return BatchModel::query()
            ->where('item_id', $item->id)
            ->exists();
    }

    public function countProducedOn(DateTimeInterface $date, ?ItemType $type = null): int
    {
        $query = BatchModel::query();

        // scoped by type as well as date, so RM/SF/FG each get their own sequence
        if ($type instanceof ItemType) {
            $query->whereHas('item', fn (Builder $i): Builder => $i->where('type', $type));
        }

        return $query
            ->whereDate('produced_at', $date->format('Y-m-d'))
            ->count();
    }
}
