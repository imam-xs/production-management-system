<?php

namespace App\Services;

use App\Enums\BatchOrigin;
use App\Enums\ItemType;
use App\Enums\MovementType;
use App\Exceptions\ItemRetiredException;
use App\Models\Batch;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\ItemStock;
use App\Repositories\Contracts\InventoryRepositoryInterface;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Raw material receiving and inventory reads.
 *
 * Production's own inventory movements (input consumption, output creation)
 * live in ProductionService, next to the transaction they are part of — see
 * that class for why the deduction is synchronous rather than handled by the
 * RabbitMQ consumer.
 */
class InventoryService
{
    public function __construct(
        private readonly InventoryRepositoryInterface $inventory,
        private readonly BatchService $batchService,
    ) {}

    /**
     * Receive a quantity of a raw material into a new purchase batch.
     *
     * The only way raw material stock increases — semi-finished and finished
     * stock only ever increase through ProductionService::execute().
     */
    public function receive(
        Item $item,
        string $quantity,
        ?DateTimeInterface $producedAt = null,
        ?string $note = null,
    ): Batch {
        if ($item->type !== ItemType::Raw) {
            throw new InvalidArgumentException(
                "{$item->name} ({$item->sku}) is not a raw material; only raw materials are received directly.",
            );
        }

        // Same rule as production: a retired material takes no new stock. See
        // ProductionService::createOrder().
        if (! $item->is_active) {
            throw ItemRetiredException::cannotReceive($item);
        }

        if (bccomp($quantity, '0', 4) <= 0) {
            throw new InvalidArgumentException('Received quantity must be greater than zero.');
        }

        return DB::transaction(function () use ($item, $quantity, $producedAt, $note): Batch {
            $producedAt ??= now();

            $batch = $this->batchService->make($item, $quantity, BatchOrigin::Purchase, null, $producedAt);

            $stock = $this->inventory->lockStockFor($item);
            $balance = $this->inventory->adjustStock($stock, $quantity);
            $this->inventory->recordMovement($item, $batch, MovementType::Receipt, $quantity, $balance, note: $note);

            return $batch;
        });
    }

    /**
     * Current stock across every item, optionally narrowed to one stage —
     * "view current inventory at every production stage". Includes items at
     * zero, which are the ones that matter most.
     *
     * @return Collection<int, Item>
     */
    public function stockLevels(?ItemType $type = null): Collection
    {
        return $this->inventory->stockLevels($type);
    }

    public function stockFor(Item $item): ItemStock
    {
        return $this->inventory->stockFor($item);
    }

    /**
     * @return LengthAwarePaginator<int, InventoryMovement>
     */
    public function movementsFor(Item $item, int $perPage = 15): LengthAwarePaginator
    {
        return $this->inventory->paginateMovements($item, $perPage);
    }
}
