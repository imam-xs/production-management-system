<?php

namespace App\Repositories\Eloquent;

use App\Enums\ItemType;
use App\Models\Item;
use App\Repositories\Contracts\ItemRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Item>
 */
class ItemRepository extends BaseRepository implements ItemRepositoryInterface
{
    /**
     * Columns a client is allowed to sort by. Anything else falls back to
     * `name`, which is what keeps an arbitrary `?sort_by=` value out of the SQL.
     *
     * @var list<string>
     */
    private const SORTABLE = ['name', 'sku', 'created_at', 'reorder_level'];

    protected function model(): Item
    {
        return new Item;
    }

    /**
     * The standard read query for any item that will be serialised.
     *
     * The three `withExists` flags answer "does anything still depend on this
     * item?" in the same round trip, so the UI can disable a Delete action the
     * server would refuse anyway. ItemService::delete() remains the authority —
     * these are a hint, never a substitute for the check.
     *
     * @return Builder<Item>
     */
    private function readQuery(): Builder
    {
        return $this->query()
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
        $item = $this->persist($attributes);

        return $item->load(['unit', 'stock'])
            ->loadExists(['batches', 'billOfMaterials', 'usedIn']);
    }

    public function update(Item $item, array $attributes): Item
    {
        return $this->applyUpdate($item, $attributes)
            ->load(['unit', 'stock'])
            ->loadExists(['batches', 'billOfMaterials', 'usedIn']);
    }

    public function delete(Item $item): void
    {
        // Soft delete — production history must keep referencing the item.
        $item->delete();
    }

    public function lowStock(?ItemType $type = null): Collection
    {
        return $this->query()
            ->with(['unit', 'stock'])
            ->when($type instanceof ItemType, fn (Builder $q): Builder => $q->where('items.type', $type))
            ->where('items.is_active', true)
            // LEFT JOIN + COALESCE, not whereHas: an item with no stock row yet
            // holds zero and is therefore *always* low, but an inner join would
            // silently exclude exactly those items.
            ->leftJoin('item_stocks', 'item_stocks.item_id', '=', 'items.id')
            ->whereRaw('COALESCE(item_stocks.quantity_on_hand, 0) <= items.reorder_level')
            ->select('items.*')
            ->orderBy('items.name')
            ->get();
    }
}
