<?php

namespace App\Services;

use App\Enums\ItemType;
use App\Models\ItemModel;
use App\Repositories\Contracts\BatchRepositoryInterface;
use App\Repositories\Contracts\BillOfMaterialRepositoryInterface;
use App\Repositories\Contracts\ItemRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ItemService
{
    public function __construct(
        private readonly ItemRepositoryInterface $items,
        private readonly BatchRepositoryInterface $batches,
        private readonly BillOfMaterialRepositoryInterface $boms,
    ) {}

    public function list(ItemType $type, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->items->paginateByType($type, $filters, $perPage);
    }

    public function allOfType(ItemType $type): Collection
    {
        return $this->items->allOfType($type);
    }

    public function findOrFail(int $id, ItemType $type): ItemModel
    {
        $item = $this->items->findByIdAndType($id, $type);

        if ($item === null) {
            throw (new ModelNotFoundException)->setModel(ItemModel::class, [$id]);
        }

        return $item;
    }

    public function create(ItemType $type, array $attributes): ItemModel
    {
        // $type wins regardless of what $attributes contains — a raw-material
        // endpoint can never be used to create a finished product.
        return $this->items->create([...$attributes, 'type' => $type]);
    }

    public function update(ItemModel $item, array $attributes): ItemModel
    {
        // type is immutable after creation: changing it would orphan the
        // item's existing batches and bill-of-materials lines, which are all
        // written against the stage it was created at.
        unset($attributes['type']);

        return $this->items->update($item, $attributes);
    }

    // delete an item — only ever one that nothing depends on
    public function delete(ItemModel $item): void
    {
        if ($this->batches->hasRemainingStock($item)) {
            abort(409, sprintf('Cannot delete %s (%s) while it still has inventory on hand.', $item->name, $item->sku));
        }

        if ($this->batches->hasAnyBatch($item)) {
            abort(409, sprintf('Cannot delete %s (%s): it appears in production history. Mark it inactive instead.', $item->name, $item->sku));
        }

        if ($this->boms->isReferenced($item)) {
            abort(409, sprintf('Cannot delete %s (%s): a bill of materials still refers to it.', $item->name, $item->sku));
        }

        $this->items->delete($item);
    }

    public function lowStock(?ItemType $type = null): Collection
    {
        return $this->items->lowStock($type);
    }
}
