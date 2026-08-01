<?php

namespace App\Exceptions;

use App\Models\Item;

/**
 * Thrown when new work is requested against an item that has been retired.
 *
 * Retirement (`is_active = false`) is how a product leaves circulation once it
 * can no longer be deleted — see ItemService::delete(). It blocks *new* stock:
 * no production runs, no receipts. It deliberately does not block consuming
 * what already exists, which is how a component is phased out rather than
 * stranded on the shelf.
 *
 * Enforced in the services rather than only by filtering the UI's dropdowns, so
 * the rule holds for any client.
 */
class ItemRetiredException extends DomainException
{
    private function __construct(
        string $message,
        private readonly string $itemSku,
    ) {
        parent::__construct($message);
    }

    public static function cannotProduce(Item $item): self
    {
        return new self(
            sprintf('%s (%s) is retired and cannot be produced. Mark it active first.', $item->name, $item->sku),
            $item->sku,
        );
    }

    public static function cannotReceive(Item $item): self
    {
        return new self(
            sprintf('%s (%s) is retired and cannot be received. Mark it active first.', $item->name, $item->sku),
            $item->sku,
        );
    }

    public function statusCode(): int
    {
        return 422;
    }

    public function context(): array
    {
        return ['item_sku' => $this->itemSku, 'reason' => 'retired'];
    }
}
