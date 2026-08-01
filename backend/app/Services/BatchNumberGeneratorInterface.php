<?php

namespace App\Services;

use App\Models\Item;
use DateTimeInterface;

/**
 * Produces human-readable batch identifiers (RM-20260730-0001).
 *
 * Kept behind an interface — unlike the repositories, this isn't needed for
 * testability so much as for the numbering *scheme* being swappable without
 * touching InventoryService or ProductionService: a per-warehouse scheme or a
 * UUID-based one is a one-line binding change away.
 */
interface BatchNumberGeneratorInterface
{
    public function generate(Item $item, DateTimeInterface $producedAt): string;
}
