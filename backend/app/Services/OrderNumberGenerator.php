<?php

namespace App\Services;

use App\Repositories\Contracts\ProductionOrderRepositoryInterface;
use DateTimeInterface;

class OrderNumberGenerator implements OrderNumberGeneratorInterface
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
