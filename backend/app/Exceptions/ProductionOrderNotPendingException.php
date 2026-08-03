<?php

namespace App\Exceptions;

use App\Models\ProductionOrder;

class ProductionOrderNotPendingException extends DomainException
{
    private function __construct(
        string $message,
        private readonly string $orderNumber,
        private readonly string $status,
    ) {
        parent::__construct($message);
    }

    public static function forOrder(ProductionOrder $order): self
    {
        return new self(
            sprintf(
                'Production order %s cannot be modified because it is already %s.',
                $order->order_number,
                $order->status->value,
            ),
            $order->order_number,
            $order->status->value,
        );
    }

    public function statusCode(): int
    {
        return 409;
    }

    public function context(): array
    {
        return [
            'order_number' => $this->orderNumber,
            'status' => $this->status,
        ];
    }
}
