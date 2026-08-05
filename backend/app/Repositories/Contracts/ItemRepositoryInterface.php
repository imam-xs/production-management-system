<?php

namespace App\Repositories\Contracts;

use App\Enums\ItemType;
use App\Models\ItemModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ItemRepositoryInterface
{
    public function paginateByType(ItemType $type, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function allOfType(ItemType $type): Collection;

    public function findByIdOrFail(int $id): ItemModel;

    public function findByIdAndType(int $id, ItemType $type): ?ItemModel;

    public function create(array $attributes): ItemModel;

    public function update(ItemModel $item, array $attributes): ItemModel;

    public function delete(ItemModel $item): void;

    public function lowStock(?ItemType $type = null): Collection;
}
