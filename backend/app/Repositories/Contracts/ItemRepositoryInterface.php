<?php

namespace App\Repositories\Contracts;

use App\Enums\ItemType;
use App\Models\ItemModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ItemRepositoryInterface
{
    /**
     * @param  array{search?: ?string, is_active?: ?bool}  $filters
     * @return LengthAwarePaginator<int, ItemModel>
     */
    public function paginateByType(ItemType $type, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * @return Collection<int, ItemModel>
     */
    public function allOfType(ItemType $type): Collection;

    /**
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findByIdOrFail(int $id): ItemModel;

    public function findByIdAndType(int $id, ItemType $type): ?ItemModel;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): ItemModel;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(ItemModel $item, array $attributes): ItemModel;

    public function delete(ItemModel $item): void;

    /**
     * @return Collection<int, ItemModel>
     */
    public function lowStock(?ItemType $type = null): Collection;
}
