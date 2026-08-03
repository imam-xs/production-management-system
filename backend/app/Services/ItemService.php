<?php

namespace App\Services;

use App\Enums\ItemType;
use App\Models\Item;
use App\Repositories\Contracts\BatchRepositoryInterface;
use App\Repositories\Contracts\BillOfMaterialRepositoryInterface;
use App\Repositories\Contracts\ItemRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * CRUD and catalogue reads for items, scoped by production stage.
 *
 * Controllers depend on this — never on ItemRepositoryInterface directly — so
 * every request goes through one dependency type and the read/write split stays
 * uniform even where a method is a thin pass-through with no business rule of
 * its own.
 */
class ItemService
{
    public function __construct(
        private readonly ItemRepositoryInterface $items,
        private readonly BatchRepositoryInterface $batches,
        private readonly BillOfMaterialRepositoryInterface $boms,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Item>
     */
    public function list(
        ItemType $type,
        ?string $search = null,
        ?bool $isActive = null,
        string $sortBy = 'name',
        string $sortDirection = 'asc',
        int $perPage = 15,
    ): LengthAwarePaginator {
        return $this->items->paginateByType($type, $search, $isActive, $sortBy, $sortDirection, $perPage);
    }

    /**
     * @return Collection<int, Item>
     */
    public function allOfType(ItemType $type): Collection
    {
        return $this->items->allOfType($type);
    }

    /**
     * @throws ModelNotFoundException
     */
    public function findOrFail(int $id, ItemType $type): Item
    {
        $item = $this->items->findByIdAndType($id, $type);

        if ($item === null) {
            throw (new ModelNotFoundException)->setModel(Item::class, [$id]);
        }

        return $item;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(ItemType $type, array $attributes): Item
    {
        // $type wins regardless of what $attributes contains — a raw-material
        // endpoint can never be used to create a finished product.
        return $this->items->create([...$attributes, 'type' => $type]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Item $item, array $attributes): Item
    {
        // type is immutable after creation: changing it would orphan the
        // item's existing batches and bill-of-materials lines, which are all
        // written against the stage it was created at.
        unset($attributes['type']);

        return $this->items->update($item, $attributes);
    }

    /**
     * Delete an item — only ever one that nothing depends on.
     *
     * Checked in order of how obvious the answer is to the person clicking:
     * stock on hand first, then production history, then recipes. All three are
     * refusals rather than cascades, because every one of them would break a
     * traceability chain that already exists.
     *
     * A product that has been used is retired by clearing `is_active`, not by
     * deleting it — the records that name it must keep resolving.
     */
    public function delete(Item $item): void
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

    /**
     * @return Collection<int, Item>
     */
    public function lowStock(?ItemType $type = null): Collection
    {
        return $this->items->lowStock($type);
    }
}
