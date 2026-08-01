<?php

namespace App\Services;

use App\Models\Item;
use App\Repositories\Contracts\BatchRepositoryInterface;
use DateTimeInterface;

/**
 * {prefix}-{Ymd}-{sequence}, e.g. RM-20260730-0001.
 *
 * The sequence is scoped to (date, item type) via `countProducedOn`, so each
 * stage's numbering starts its own count for the day rather than sharing one
 * counter across raw/semi/finished. The number is a candidate, not a
 * guarantee — see BatchFactory for how a collision under concurrent receipts
 * is handled.
 */
class BatchNumberGenerator implements BatchNumberGeneratorInterface
{
    public function __construct(
        private readonly BatchRepositoryInterface $batches,
    ) {}

    public function generate(Item $item, DateTimeInterface $producedAt): string
    {
        $sequence = $this->batches->countProducedOn($producedAt, $item->type) + 1;

        return sprintf('%s-%s-%04d', $item->type->batchPrefix(), $producedAt->format('Ymd'), $sequence);
    }
}
