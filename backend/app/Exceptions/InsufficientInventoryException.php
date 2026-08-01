<?php

namespace App\Exceptions;

use App\Models\Item;

/**
 * Thrown by InventoryAllocator before any write happens, when an item's
 * available batches cannot cover the quantity a production run requires.
 *
 * This is the exception that makes "prevent production if inventory is
 * insufficient" real rather than aspirational — it fires inside the same
 * locked transaction that would otherwise deduct stock, so the order stays
 * Pending and nothing partial is left behind.
 */
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
