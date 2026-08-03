<?php

namespace App\Services;

use App\Models\BatchModel;
use App\Models\ItemModel;
use App\Repositories\Contracts\BatchRepositoryInterface;
use Illuminate\Validation\ValidationException;

// plans which batches cover a required quantity, oldest first
// must run inside a transaction — lockAvailableFifo takes row locks

class InventoryAllocator
{
    public function __construct(
        private readonly BatchRepositoryInterface $batches,
    ) {}

    /**
     * @return list<array{batch: BatchModel, quantity: string}>
     */
    public function allocate(ItemModel $item, string $requiredQuantity): array
    {
        $available = $this->batches->lockAvailableFifo($item->id);

        // summed from the locked rows — a separate SELECT SUM would read a stale snapshot
        $totalAvailable = '0';
        foreach ($available as $batch) {
            $totalAvailable = bcadd($totalAvailable, (string) $batch->quantity_remaining, 4);
        }

        if (bccomp($totalAvailable, $requiredQuantity, 4) < 0) {
            throw ValidationException::withMessages([
                'planned_quantity' => sprintf(
                    'Insufficient inventory for %s (%s): %s required, %s available, short by %s.',
                    $item->name,
                    $item->sku,
                    $requiredQuantity,
                    $totalAvailable,
                    bcsub($requiredQuantity, $totalAvailable, 4),
                ),
            ]);
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
