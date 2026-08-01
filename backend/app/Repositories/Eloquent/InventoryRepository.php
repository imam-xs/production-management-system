<?php

namespace App\Repositories\Eloquent;

use App\Enums\ItemType;
use App\Enums\MovementType;
use App\Models\Batch;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\ItemStock;
use App\Repositories\Contracts\InventoryRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends BaseRepository<ItemStock>
 */
class InventoryRepository extends BaseRepository implements InventoryRepositoryInterface
{
    protected function model(): ItemStock
    {
        return new ItemStock;
    }

    public function snapshot(?ItemType $type = null): Collection
    {
        // Driven by `items`, not by `item_stocks`. The stock row is only created
        // on an item's first movement, so querying item_stocks would silently
        // omit every item sitting at zero — which is precisely the stock level a
        // plant most needs to see. Callers read the quantity through the
        // (possibly absent) `stock` relation and treat missing as zero.
        return Item::query()
            ->with(['unit', 'stock'])
            ->when($type instanceof ItemType, fn (Builder $q): Builder => $q->where('type', $type))
            ->orderBy('type')
            ->orderBy('name')
            ->get();
    }

    public function stockFor(Item $item): ItemStock
    {
        return $this->query()->firstOrCreate(
            ['item_id' => $item->id],
            ['quantity_on_hand' => '0'],
        );
    }

    public function lockStockFor(Item $item): ItemStock
    {
        // Ensure the row exists before locking — you cannot lock what is not
        // there, and a missing row is the normal state for a brand new item.
        $this->stockFor($item);

        /** @var ItemStock $locked */
        $locked = $this->query()
            ->where('item_id', $item->id)
            ->lockForUpdate()
            ->firstOrFail();

        return $locked;
    }

    public function adjustStock(ItemStock $stock, string $signedQuantity): string
    {
        $balance = bcadd((string) $stock->quantity_on_hand, $signedQuantity, 4);

        $stock->quantity_on_hand = $balance;
        $stock->save();

        return $balance;
    }

    public function recordMovement(
        Item $item,
        ?Batch $batch,
        MovementType $type,
        string $signedQuantity,
        string $balanceAfter,
        ?Model $reference = null,
        ?string $note = null,
    ): InventoryMovement {
        return InventoryMovement::query()->create([
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

    public function paginateMovements(Item $item, int $perPage = 15): LengthAwarePaginator
    {
        return InventoryMovement::query()
            ->with(['batch', 'item.unit'])
            ->where('item_id', $item->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function ledgerBalance(Item $item): string
    {
        $sum = InventoryMovement::query()
            ->where('item_id', $item->id)
            ->sum('quantity');

        return (string) $sum;
    }
}
