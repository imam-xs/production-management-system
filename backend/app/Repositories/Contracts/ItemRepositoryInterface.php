<?php

namespace App\Repositories\Contracts;

use App\Enums\ItemType;
use App\Models\Item;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Data access for the item catalogue.
 *
 * Every read is type-scoped: the three REST resources (raw materials,
 * semi-finished, finished) are the same queries with a different ItemType, which
 * is what lets one repository back all three controllers without duplication.
 */
interface ItemRepositoryInterface
{
    /**
     * Paginated listing for one production stage.
     *
     * Sorting is whitelisted inside the implementation, so an arbitrary
     * `?sort_by=` value can never reach SQL.
     *
     * @return LengthAwarePaginator<int, Item>
     */
    public function paginateByType(
        ItemType $type,
        ?string $search = null,
        ?bool $isActive = null,
        string $sortBy = 'name',
        string $sortDirection = 'asc',
        int $perPage = 15,
    ): LengthAwarePaginator;

    /**
     * @return Collection<int, Item>
     */
    public function allOfType(ItemType $type): Collection;

    public function findById(int $id): ?Item;

    /**
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findByIdOrFail(int $id): Item;

    /**
     * Scoped lookup — used so a raw-material endpoint can never return, or
     * mutate, a finished product that happens to share an id.
     */
    public function findByIdAndType(int $id, ItemType $type): ?Item;

    public function findBySku(string $sku): ?Item;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Item;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Item $item, array $attributes): Item;

    public function delete(Item $item): void;

    /**
     * Items whose on-hand quantity has fallen to or below their reorder level.
     *
     * @return Collection<int, Item>
     */
    public function lowStock(?ItemType $type = null): Collection;
}
