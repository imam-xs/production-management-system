<?php

namespace App\Services;

use App\Enums\BatchOrigin;
use App\Models\Batch;
use App\Models\Item;
use App\Models\ProductionOrder;
use App\Repositories\Contracts\BatchRepositoryInterface;
use DateTimeInterface;
use Illuminate\Database\QueryException;

/**
 * Creates a batch with a guaranteed-unique batch number.
 *
 * One reason to change: "batch numbers must be unique even under concurrent
 * writes." Used by both InventoryService (purchase receipts) and
 * ProductionService (production output), which is why it is its own class
 * rather than duplicated in each.
 *
 * The `batch_number` unique index is the actual guarantee; two concurrent
 * receipts of *different* items of the same type on the same day can compute
 * the same candidate number before either commits (`BatchNumberGenerator`
 * counts existing rows, which is inherently racy without a lock of its own).
 * This retry loop is what turns that DB-level rejection into a clean second
 * attempt instead of a 500.
 */
class BatchFactory
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly BatchRepositoryInterface $batches,
        private readonly BatchNumberGenerator $batchNumbers,
    ) {}

    public function make(
        Item $item,
        string $quantity,
        BatchOrigin $origin,
        ?ProductionOrder $producedBy,
        DateTimeInterface $producedAt,
    ): Batch {
        $attempt = 0;

        do {
            $attempt++;

            try {
                return $this->batches->create([
                    'batch_number' => $this->batchNumbers->generate($item, $producedAt),
                    'item_id' => $item->id,
                    'quantity_produced' => $quantity,
                    'quantity_remaining' => $quantity,
                    'origin' => $origin,
                    'production_order_id' => $producedBy?->id,
                    'produced_at' => $producedAt,
                ]);
            } catch (QueryException $e) {
                if ($attempt >= self::MAX_ATTEMPTS || ! $this->isDuplicateBatchNumber($e)) {
                    throw $e;
                }
            }
        } while (true);
    }

    private function isDuplicateBatchNumber(QueryException $e): bool
    {
        $isDuplicateEntry = ($e->errorInfo[1] ?? null) === 1062;

        return $isDuplicateEntry && str_contains($e->getMessage(), 'batches_batch_number_unique');
    }
}
