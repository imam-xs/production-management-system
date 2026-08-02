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

interface InventoryRepositoryInterface
{
    /**
     * @return Collection<int, Item>
     */
    public function stockLevels(?ItemType $type = null): Collection;

    public function stockFor(Item $item): ItemStock;

    // locked for update
    public function lockStockFor(Item $item): ItemStock;

    public function adjustStock(ItemStock $stock, string $signedQuantity): string;

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
