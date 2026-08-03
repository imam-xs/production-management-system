<?php

namespace App\Exceptions;

use App\Models\Item;

class InsufficientInventoryException extends DomainException
{
    private function __construct(
        string $message,
        private readonly string $itemSku,
        private readonly string $itemName,
        private readonly string $required,
        private readonly string $available,
    ) {
        parent::__construct($message);
    }

    public static function forItem(Item $item, string $required, string $available): self
    {
        return new self(
            sprintf(
                'Insufficient inventory for %s (%s): required %s, available %s.',
                $item->name,
                $item->sku,
                $required,
                $available,
            ),
            $item->sku,
            $item->name,
            $required,
            $available,
        );
    }

    public function statusCode(): int
    {
        return 422;
    }

    public function context(): array
    {
        return [
            'item_sku' => $this->itemSku,
            'item_name' => $this->itemName,
            'required_quantity' => $this->required,
            'available_quantity' => $this->available,
            'shortfall' => bcsub($this->required, $this->available, 4),
        ];
    }
}
