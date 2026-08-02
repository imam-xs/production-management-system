<?php

namespace App\Services;

use App\Repositories\Contracts\ProductionOrderRepositoryInterface;
use DateTimeInterface;

/**
 * PO-{Ymd}-{sequence}, e.g. PO-20260730-0001.
 *
 * The sequence counts orders created on the same day. Like the batch number it
 * is a candidate rather than a guarantee — the unique index on `order_number`
 * is what actually enforces uniqueness.
 *
 * Lives in one class rather than in the controller because ProductionService
 * is also called from DemoProductionSeeder, which never goes through HTTP.
 */
class OrderNumberGenerator
{
    public function __construct(
        private readonly ProductionOrderRepositoryInterface $orders,
    ) {}

    public function generate(DateTimeInterface $createdAt): string
    {
        $sequence = $this->orders->countCreatedOn($createdAt) + 1;

        return sprintf('PO-%s-%04d', $createdAt->format('Ymd'), $sequence);
    }
}
