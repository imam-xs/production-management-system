<?php

namespace App\Repositories\Contracts;

use App\Enums\ItemType;
use App\Enums\MovementType;
use App\Models\Batch;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\ItemStock;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Data access for the inventory ledger and its cached balances.
 *
 * `item_stocks` and `inventory_movements` are owned by one repository because
 * they are a single unit of consistency: no balance may change without the
 * corresponding ledger row, and both writes happen in the same transaction.
 * Splitting them across two repositories would invite callers to update one and
 * forget the other.
 */
interface InventoryRepositoryInterface
{
    /**
     * Every item with its current stock level, optionally narrowed to one
     * production stage — this is what "view inventory at every stage" reads.
     *
     * Returns items rather than stock rows so an item at zero is still listed;
     * its `stock` relation may be null and must be read as zero.
     *
     * @return Collection<int, Item>
     */
    public function snapshot(?ItemType $type = null): Collection;

    public function stockFor(Item $item): ItemStock;

    /**
     * Fetch (creating if absent) an item's stock row, **locked for update**.
     * Must be called inside a transaction.
     */
    public function lockStockFor(Item $item): ItemStock;

    /**
     * Apply a signed delta to the cached balance and return the new value as a
     * decimal string.
     */
    public function adjustStock(ItemStock $stock, string $signedQuantity): string;

    /**
     * Append one row to the ledger. `$balanceAfter` is supplied by the caller
     * because only it knows the balance produced by the surrounding transaction.
     */
    public function recordMovement(
        Item $item,
        ?Batch $batch,
        MovementType $type,
        string $signedQuantity,
        string $balanceAfter,
        ?Model $reference = null,
        ?string $note = null,
    ): InventoryMovement;

    /**
     * @return LengthAwarePaginator<int, InventoryMovement>
     */
    public function paginateMovements(Item $item, int $perPage = 15): LengthAwarePaginator;
}
