<?php

namespace App\Repositories\Contracts;

use App\Enums\ItemType;
use App\Enums\MovementType;
use App\Models\BatchModel;
use App\Models\InventoryMovementModel;
use App\Models\ItemModel;
use App\Models\ItemStockModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface InventoryRepositoryInterface
{
    public function stockLevels(?ItemType $type = null): Collection;

    public function stockFor(ItemModel $item): ItemStockModel;

    // locked for update
    public function lockStockFor(ItemModel $item): ItemStockModel;

    public function adjustStock(ItemStockModel $stock, string $signedQuantity): string;

    public function recordMovement(
        ItemModel $item,
        ?BatchModel $batch,
        MovementType $type,
        string $signedQuantity,
        string $balanceAfter,
        ?Model $reference = null,
        ?string $note = null,
    ): InventoryMovementModel;

    public function paginateMovements(ItemModel $item, int $perPage = 15): LengthAwarePaginator;
}
