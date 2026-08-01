<?php

namespace App\Exceptions;

use App\Models\Item;

/**
 * Thrown when an item cannot be deleted because something still depends on it.
 *
 * A product record is only removable while it is genuinely unused. Once a batch
 * has been made from it, or a recipe names it, deleting it would leave that
 * history pointing at nothing — and traceability is the one thing this system
 * exists to guarantee. Retiring a product that has been used is a different
 * operation: clear `is_active`, which keeps every record intact.
 */
class ItemHasStockException extends DomainException
{
    private function __construct(
        string $message,
        private readonly string $itemSku,
        private readonly string $reason,
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
            'stock_on_hand',
        );
    }

    public static function usedInProduction(Item $item): self
    {
        return new self(
            sprintf(
                'Cannot delete %s (%s): it appears in production history. Mark it inactive instead.',
                $item->name,
                $item->sku,
            ),
            $item->sku,
            'production_history',
        );
    }

    public static function usedInRecipe(Item $item): self
    {
        return new self(
            sprintf(
                'Cannot delete %s (%s): a bill of materials still refers to it.',
                $item->name,
                $item->sku,
            ),
            $item->sku,
            'bill_of_materials',
        );
    }

    public function statusCode(): int
    {
        return 409;
    }

    public function context(): array
    {
        return ['item_sku' => $this->itemSku, 'reason' => $this->reason];
    }
}
