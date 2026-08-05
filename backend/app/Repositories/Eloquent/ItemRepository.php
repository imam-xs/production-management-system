<?php

namespace App\Repositories\Eloquent;

use App\Enums\ItemType;
use App\Models\ItemModel;
use App\Repositories\Contracts\ItemRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ItemRepository implements ItemRepositoryInterface
{
    private function readQuery(): Builder
    {
        return ItemModel::query()
            ->with(['unit', 'stock'])
            ->withExists(['batches', 'billOfMaterials', 'usedIn']);
    }

    public function paginateByType(ItemType $type, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->readQuery()->where('type', $type);

        $isActive = $filters['is_active'] ?? null;
        $search = $filters['search'] ?? null;

        if ($isActive !== null) {
            $query->where('is_active', $isActive);
        }

        // one search box, two columns: the inner grouping keeps the OR from
        // swallowing the type filter above
        if ($search !== null && $search !== '') {
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    public function allOfType(ItemType $type): Collection
    {
        return $this->readQuery()
            ->where('type', $type)
            ->orderBy('name')
            ->get();
    }

    public function findByIdOrFail(int $id): ItemModel
    {
        return $this->readQuery()->findOrFail($id);
    }

    public function findByIdAndType(int $id, ItemType $type): ?ItemModel
    {
        return $this->readQuery()
            ->where('type', $type)
            ->find($id);
    }

    public function create(array $attributes): ItemModel
    {
        $item = ItemModel::query()->create($attributes);

        return $item->load(['unit', 'stock'])
            ->loadExists(['batches', 'billOfMaterials', 'usedIn']);
    }

    public function update(ItemModel $item, array $attributes): ItemModel
    {
        $item->fill($attributes)->save();

        return $item->refresh()
            ->load(['unit', 'stock'])
            ->loadExists(['batches', 'billOfMaterials', 'usedIn']);
    }

    public function delete(ItemModel $item): void
    {
        $item->delete();
    }

    public function lowStock(?ItemType $type = null): Collection
    {
        $query = ItemModel::query()->with(['unit', 'stock']);

        if ($type instanceof ItemType) {
            $query->where('items.type', $type);
        }

        return $query
            ->where('items.is_active', true)
            ->leftJoin('item_stocks', 'item_stocks.item_id', '=', 'items.id')
            ->whereRaw('COALESCE(item_stocks.quantity_on_hand, 0) <= items.reorder_level')
            ->select('items.*')
            ->orderBy('items.name')
            ->get();
    }
}
