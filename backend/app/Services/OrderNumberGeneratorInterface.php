<?php

namespace App\Services;

use DateTimeInterface;

/**
 * Produces human-readable production order identifiers (PO-20260730-0001).
 */
interface OrderNumberGeneratorInterface
{
    public function generate(DateTimeInterface $createdAt): string;
}
