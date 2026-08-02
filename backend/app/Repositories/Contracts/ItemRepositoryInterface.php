<?php

namespace App\Repositories\Contracts;

use App\Enums\ItemType;
use App\Models\Item;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ItemRepositoryInterface
{
    /**
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

    /**
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findByIdOrFail(int $id): Item;

    public function findByIdAndType(int $id, ItemType $type): ?Item;

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
     * @return Collection<int, Item>
     */
    public function lowStock(?ItemType $type = null): Collection;
}
