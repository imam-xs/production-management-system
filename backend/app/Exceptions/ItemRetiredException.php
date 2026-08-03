<?php

namespace App\Exceptions;

use App\Models\Item;

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
