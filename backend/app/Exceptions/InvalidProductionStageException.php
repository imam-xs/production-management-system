<?php

namespace App\Exceptions;

use App\Models\Item;

/**
 * Thrown when a production request doesn't correspond to a legal stage: the
 * output item is a raw material (raw materials are received, never produced),
 * or it has no bill-of-materials recipe defined yet.
 */
class InvalidProductionStageException extends DomainException
{
    private function __construct(
        string $message,
        private readonly string $itemSku,
        private readonly string $reason,
    ) {
        parent::__construct($message);
    }

    public static function itemIsNotProduced(Item $item): self
    {
        return new self(
            sprintf('%s (%s) is a raw material and cannot be the output of a production run.', $item->name, $item->sku),
            $item->sku,
            'not_produced',
        );
    }

    public static function missingRecipe(Item $item): self
    {
        return new self(
            sprintf('%s (%s) has no bill-of-materials recipe, so production cannot be planned.', $item->name, $item->sku),
            $item->sku,
            'missing_recipe',
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
            'reason' => $this->reason,
        ];
    }
}
