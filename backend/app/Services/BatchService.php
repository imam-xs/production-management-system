<?php

namespace App\Services;

use App\Enums\BatchOrigin;
use App\Enums\ItemType;
use App\Models\Batch;
use App\Models\Item;
use App\Models\ProductionOrder;
use App\Repositories\Contracts\BatchRepositoryInterface;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;

// batches: listing them, and creating them with a guaranteed-unique number
class BatchService
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly BatchRepositoryInterface $batches,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Batch>
     */
    public function list(
        ?string $search = null,
        ?int $itemId = null,
        ?ItemType $itemType = null,
        ?BatchOrigin $origin = null,
        bool $availableOnly = false,
        int $perPage = 15,
    ): LengthAwarePaginator {
        return $this->batches->paginate($search, $itemId, $itemType, $origin, $availableOnly, $perPage);
    }

    /**
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findOrFail(int $id): Batch
    {
        return $this->batches->findByIdOrFail($id);
    }

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
                    'batch_number' => $this->nextNumber($item, $producedAt),
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

    // prefix-Ymd-sequence, e.g. RM-20260730-0001 — a candidate, not a guarantee:
    // the unique index on batch_number is what actually enforces uniqueness
    private function nextNumber(Item $item, DateTimeInterface $producedAt): string
    {
        $sequence = $this->batches->countProducedOn($producedAt, $item->type) + 1;

        return sprintf('%s-%s-%04d', $item->type->batchPrefix(), $producedAt->format('Ymd'), $sequence);
    }

    private function isDuplicateBatchNumber(QueryException $e): bool
    {
        $isDuplicateEntry = ($e->errorInfo[1] ?? null) === 1062;

        return $isDuplicateEntry && str_contains($e->getMessage(), 'batches_batch_number_unique');
    }
}
