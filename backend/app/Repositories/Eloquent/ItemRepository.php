<?php

namespace App\Repositories\Eloquent;

use App\Enums\ItemType;
use App\Models\Item;
use App\Repositories\Contracts\ItemRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ItemRepository implements ItemRepositoryInterface
{
    /**
     * @var list<string>
     */
    private const SORTABLE = ['name', 'sku', 'created_at', 'reorder_level'];

    /**
     * @return Builder<Item>
     */
    private function readQuery(): Builder
    {
        return Item::query()
            ->with(['unit', 'stock'])
            ->withExists(['batches', 'billOfMaterials', 'usedIn']);
    }

    public function paginateByType(
        ItemType $type,
        ?string $search = null,
        ?bool $isActive = null,
        string $sortBy = 'name',
        string $sortDirection = 'asc',
        int $perPage = 15,
    ): LengthAwarePaginator {
        return $this->readQuery()
            ->where('type', $type)
            ->when($isActive !== null, fn (Builder $q): Builder => $q->where('is_active', $isActive))
            ->when(
                $search !== null && $search !== '',
                fn (Builder $q): Builder => $q->where(function (Builder $inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                }),
            )
            ->orderBy($this->sortColumn($sortBy), $this->sortDirection($sortDirection))
            ->paginate($perPage);
    }

    private function sortColumn(string $requested): string
    {
        return in_array($requested, self::SORTABLE, true) ? $requested : 'name';
    }

    private function sortDirection(string $requested): string
    {
        return strtolower($requested) === 'desc' ? 'desc' : 'asc';
    }

    public function allOfType(ItemType $type): Collection
    {
        return $this->readQuery()
            ->where('type', $type)
            ->orderBy('name')
            ->get();
    }

    public function findByIdOrFail(int $id): Item
    {
        return $this->readQuery()->findOrFail($id);
    }

    public function findByIdAndType(int $id, ItemType $type): ?Item
    {
        return $this->readQuery()
            ->where('type', $type)
            ->find($id);
    }

    public function create(array $attributes): Item
    {
        $item = Item::query()->create($attributes);

        return $item->load(['unit', 'stock'])
            ->loadExists(['batches', 'billOfMaterials', 'usedIn']);
    }

    public function update(Item $item, array $attributes): Item
    {
        $item->fill($attributes)->save();

        return $item->refresh()
            ->load(['unit', 'stock'])
            ->loadExists(['batches', 'billOfMaterials', 'usedIn']);
    }

    public function delete(Item $item): void
    {
        $item->delete();
    }

    public function lowStock(?ItemType $type = null): Collection
    {
        return Item::query()
            ->with(['unit', 'stock'])
            ->when($type instanceof ItemType, fn (Builder $q): Builder => $q->where('items.type', $type))
            ->where('items.is_active', true)
            ->leftJoin('item_stocks', 'item_stocks.item_id', '=', 'items.id')
            ->whereRaw('COALESCE(item_stocks.quantity_on_hand, 0) <= items.reorder_level')
            ->select('items.*')
            ->orderBy('items.name')
            ->get();
    }
}
