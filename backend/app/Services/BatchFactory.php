<?php

namespace App\Services;

use App\Enums\BatchOrigin;
use App\Models\Batch;
use App\Models\Item;
use App\Models\ProductionOrder;
use App\Repositories\Contracts\BatchRepositoryInterface;
use DateTimeInterface;
use Illuminate\Database\QueryException;

// creates a batch with a guaranteed-unique batch number
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
        for ($attempt = 1; ; $attempt++) {
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
                // a clashing batch number is worth another try; anything else is not
                if ($attempt >= self::MAX_ATTEMPTS || ! $this->isDuplicateBatchNumber($e)) {
                    throw $e;
                }
            }
        }
    }

    private function isDuplicateBatchNumber(QueryException $e): bool
    {
        $isDuplicateEntry = ($e->errorInfo[1] ?? null) === 1062;

        return $isDuplicateEntry && str_contains($e->getMessage(), 'batches_batch_number_unique');
    }
}
