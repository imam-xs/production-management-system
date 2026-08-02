<?php

namespace App\Repositories\Eloquent;

use App\Enums\BatchOrigin;
use App\Enums\ItemType;
use App\Models\Batch;
use App\Models\Item;
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
        return Batch::query()
            ->with(['item.unit', 'productionOrder'])
            ->when($itemId !== null, fn (Builder $q): Builder => $q->where('item_id', $itemId))
            ->when(
                $itemType instanceof ItemType,
                fn (Builder $q): Builder => $q->whereHas('item', fn (Builder $i): Builder => $i->where('type', $itemType)),
            )
            ->when($origin instanceof BatchOrigin, fn (Builder $q): Builder => $q->where('origin', $origin))
            ->when($availableOnly, fn (Builder $q): Builder => $q->where('quantity_remaining', '>', 0))
            ->when(
                $search !== null && $search !== '',
                fn (Builder $q): Builder => $q->where('batch_number', 'like', "%{$search}%"),
            )
            ->orderByDesc('produced_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findByIdOrFail(int $id): Batch
    {
        return Batch::query()->with(['item.unit', 'productionOrder'])->findOrFail($id);
    }

    public function create(array $attributes): Batch
    {
        return Batch::query()->create($attributes);
    }

    public function lockAvailableFifo(int $itemId): Collection
    {
        return Batch::query()
            ->where('item_id', $itemId)
            ->where('quantity_remaining', '>', 0)
            // Oldest stock leaves first; id breaks ties deterministically so the
            // allocation plan is reproducible.
            ->orderBy('produced_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    public function decrementRemaining(Batch $batch, string $quantity): void
    {
        $batch->quantity_remaining = bcsub((string) $batch->quantity_remaining, $quantity, 4);
        $batch->save();
    }

    public function hasRemainingStock(Item $item): bool
    {
        return Batch::query()
            ->where('item_id', $item->id)
            ->where('quantity_remaining', '>', 0)
            ->exists();
    }

    public function hasAnyBatch(Item $item): bool
    {
        return Batch::query()
            ->where('item_id', $item->id)
            ->exists();
    }

    public function countProducedOn(DateTimeInterface $date, ?ItemType $type = null): int
    {
        return Batch::query()
            ->when(
                $type instanceof ItemType,
                fn (Builder $q): Builder => $q->whereHas('item', fn (Builder $i): Builder => $i->where('type', $type)),
            )
            ->whereDate('produced_at', $date->format('Y-m-d'))
            ->count();
    }
}
