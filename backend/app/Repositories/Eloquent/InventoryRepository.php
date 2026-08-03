<?php

namespace App\Repositories\Eloquent;

use App\Enums\ItemType;
use App\Enums\MovementType;
use App\Models\BatchModel;
use App\Models\InventoryMovementModel;
use App\Models\ItemModel;
use App\Models\ItemStockModel;
use App\Repositories\Contracts\InventoryRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class InventoryRepository implements InventoryRepositoryInterface
{
    public function stockLevels(?ItemType $type = null): Collection
    {
        return ItemModel::query()
            ->with(['unit', 'stock'])
            ->when($type instanceof ItemType, fn (Builder $q): Builder => $q->where('type', $type))
            ->orderBy('type')
            ->orderBy('name')
            ->get();
    }

    public function stockFor(ItemModel $item): ItemStockModel
    {
        return ItemStockModel::query()->firstOrCreate(
            ['item_id' => $item->id],
            ['quantity_on_hand' => '0'],
        );
    }

    public function lockStockFor(ItemModel $item): ItemStockModel
    {
        $this->stockFor($item);

        /** @var ItemStockModel $locked */
        $locked = ItemStockModel::query()
            ->where('item_id', $item->id)
            ->lockForUpdate()
            ->firstOrFail();

        return $locked;
    }

    public function adjustStock(ItemStockModel $stock, string $signedQuantity): string
    {
        $balance = bcadd((string) $stock->quantity_on_hand, $signedQuantity, 4);

        $stock->quantity_on_hand = $balance;
        $stock->save();

        return $balance;
    }

    public function recordMovement(
        ItemModel $item,
        ?BatchModel $batch,
        MovementType $type,
        string $signedQuantity,
        string $balanceAfter,
        ?Model $reference = null,
        ?string $note = null,
    ): InventoryMovementModel {
        return InventoryMovementModel::query()->create([
            'item_id' => $item->id,
            'batch_id' => $batch?->id,
            'type' => $type,
            'quantity' => $signedQuantity,
            'balance_after' => $balanceAfter,
            'reference_type' => $reference === null ? null : $reference->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'note' => $note,
        ]);
    }

    public function paginateMovements(ItemModel $item, int $perPage = 15): LengthAwarePaginator
    {
        return InventoryMovementModel::query()
            ->with(['batch', 'item.unit'])
            ->where('item_id', $item->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
