<?php

namespace App\Services;

use App\Exceptions\InsufficientInventoryException;
use App\Models\Batch;
use App\Models\Item;
use App\Repositories\Contracts\BatchRepositoryInterface;

/**
 * Plans which batches of an item to draw from to cover a required quantity.
 *
 * One reason to change: the FIFO allocation algorithm. Kept apart from
 * ProductionService so the "which batches, how much of each" question is
 * testable and readable on its own, separate from the transaction that acts on
 * the plan.
 *
 * Must be called inside a transaction — `lockAvailableFifo` takes row locks
 * that only make sense as part of one.
 */
class InventoryAllocator
{
    public function __construct(
        private readonly BatchRepositoryInterface $batches,
    ) {}

    /**
     * @return list<array{batch: Batch, quantity: string}>
     *
     * @throws InsufficientInventoryException
     */
    public function allocate(Item $item, string $requiredQuantity): array
    {
        $available = $this->batches->lockAvailableFifo($item->id);

        $remaining = $requiredQuantity;
        $totalAvailable = '0';
        $plan = [];

        foreach ($available as $batch) {
            // Accumulated from the locked rows themselves rather than from a
            // separate SUM query. That matters under concurrency: InnoDB's
            // default REPEATABLE READ isolation means a *non-locking* read
            // returns the snapshot taken when this transaction began, while the
            // locking read above returns the latest committed data. A fresh
            // SELECT SUM here would therefore report pre-contention stock and
            // produce a nonsensical error like "required 22.5, available 27.5"
            // on the very request that just lost the race.
            $totalAvailable = bcadd($totalAvailable, (string) $batch->quantity_remaining, 4);

            if (bccomp($remaining, '0', 4) <= 0) {
                continue;
            }

            $take = bccomp((string) $batch->quantity_remaining, $remaining, 4) < 0
                ? (string) $batch->quantity_remaining
                : $remaining;

            $plan[] = ['batch' => $batch, 'quantity' => $take];
            $remaining = bcsub($remaining, $take, 4);
        }

        if (bccomp($remaining, '0', 4) > 0) {
            throw InsufficientInventoryException::forItem($item, $requiredQuantity, $totalAvailable);
        }

        return $plan;
    }
}
