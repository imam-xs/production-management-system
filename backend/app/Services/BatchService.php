<?php

namespace App\Services;

use App\Enums\BatchOrigin;
use App\Models\BatchModel;
use App\Models\ItemModel;
use App\Models\ProductionOrderModel;
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

    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->batches->paginate($filters, $perPage);
    }

    public function findOrFail(int $id): BatchModel
    {
        return $this->batches->findByIdOrFail($id);
    }

    public function make(
        ItemModel $item,
        string $quantity,
        BatchOrigin $origin,
        ?ProductionOrderModel $producedBy,
        DateTimeInterface $producedAt,
    ): BatchModel {
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
    private function nextNumber(ItemModel $item, DateTimeInterface $producedAt): string
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
