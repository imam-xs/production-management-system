<?php

namespace App\Services;

use App\Enums\BatchOrigin;
use App\Enums\ItemType;
use App\Enums\MovementType;
use App\Models\BatchModel;
use App\Models\ItemModel;
use App\Models\ItemStockModel;
use App\Repositories\Contracts\InventoryRepositoryInterface;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// raw material receiving and inventory reads; production's own stock movements
// live in ProductionService, inside the transaction they belong to
class InventoryService
{
    public function __construct(
        private readonly InventoryRepositoryInterface $inventory,
        private readonly BatchService $batchService,
    ) {}

    // the only way raw material stock increases; every other stage grows only
    // through ProductionService::execute()
    public function receive(
        ItemModel $item,
        string $quantity,
        ?DateTimeInterface $producedAt = null,
        ?string $note = null,
    ): BatchModel {
        if ($item->type !== ItemType::Raw) {
            throw ValidationException::withMessages([
                'item_id' => "{$item->name} ({$item->sku}) is not a raw material; only raw materials are received directly.",
            ]);
        }

        // same rule as production: a retired material takes no new stock
        // see ProductionService::createOrder().
        if (! $item->is_active) {
            throw ValidationException::withMessages([
                'item_id' => sprintf('%s (%s) is retired and cannot be received. Mark it active first.', $item->name, $item->sku),
            ]);
        }

        if (bccomp($quantity, '0', 4) <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Received quantity must be greater than zero.',
            ]);
        }

        return DB::transaction(function () use ($item, $quantity, $producedAt, $note): BatchModel {
            $producedAt ??= now();

            $batch = $this->batchService->make($item, $quantity, BatchOrigin::Purchase, null, $producedAt);

            $stock = $this->inventory->lockStockFor($item);
            $balance = $this->inventory->adjustStock($stock, $quantity);
            $this->inventory->recordMovement($item, $batch, MovementType::Receipt, $quantity, $balance, note: $note);

            return $batch;
        });
    }

    public function stockLevels(?ItemType $type = null): Collection
    {
        return $this->inventory->stockLevels($type);
    }

    public function stockFor(ItemModel $item): ItemStockModel
    {
        return $this->inventory->stockFor($item);
    }

    public function movementsFor(ItemModel $item, int $perPage = 15): LengthAwarePaginator
    {
        return $this->inventory->paginateMovements($item, $perPage);
    }
}
