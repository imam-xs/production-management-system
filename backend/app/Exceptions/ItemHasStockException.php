<?php

namespace App\Exceptions;

use App\Models\Item;

/**
 * Thrown when deleting an item that still has batches with remaining stock.
 * Deleting it would sever the trail those batches point back through.
 */
class ItemHasStockException extends DomainException
{
    private function __construct(
        string $message,
        private readonly string $itemSku,
    ) {
        parent::__construct($message);
    }

    public static function forItem(Item $item): self
    {
        return new self(
            sprintf(
                'Cannot delete %s (%s) while it still has inventory on hand.',
                $item->name,
                $item->sku,
            ),
            $item->sku,
        );
    }

    public function statusCode(): int
    {
        return 409;
    }

    public function context(): array
    {
        return ['item_sku' => $this->itemSku];
    }
}
