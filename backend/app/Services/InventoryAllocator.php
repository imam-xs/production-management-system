<?php

namespace App\Services;

use App\Exceptions\InsufficientInventoryException;
use App\Models\Batch;
use App\Models\Item;
use App\Repositories\Contracts\BatchRepositoryInterface;

// plans which batches cover a required quantity, oldest first
// must run inside a transaction — lockAvailableFifo takes row locks

class InventoryAllocator
{
    public function __construct(
        private readonly BatchRepositoryInterface $batches,
    ) {}

    /**
     * @return list<array{batch: Batch, quantity: string}>
     */
    public function allocate(Item $item, string $requiredQuantity): array
    {
        $available = $this->batches->lockAvailableFifo($item->id);

        // summed from the locked rows — a separate SELECT SUM would read a stale snapshot
        $totalAvailable = '0';
        foreach ($available as $batch) {
            $totalAvailable = bcadd($totalAvailable, (string) $batch->quantity_remaining, 4);
        }

        if (bccomp($totalAvailable, $requiredQuantity, 4) < 0) {
            throw InsufficientInventoryException::forItem($item, $requiredQuantity, $totalAvailable);
        }

        $plan = [];
        $remaining = $requiredQuantity;

        foreach ($available as $batch) {
            if (bccomp($remaining, '0', 4) <= 0) {
                break;
            }

            // take whichever is smaller: what this batch holds, or what is left to cover
            $take = bccomp((string) $batch->quantity_remaining, $remaining, 4) < 0
                ? (string) $batch->quantity_remaining
                : $remaining;

            $plan[] = ['batch' => $batch, 'quantity' => $take];
            $remaining = bcsub($remaining, $take, 4);
        }

        return $plan;
    }
}
