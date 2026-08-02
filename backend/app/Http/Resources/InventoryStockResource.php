<?php

namespace App\Http\Resources;

use App\Models\Item;
use App\Models\ItemStock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the inventory view: an item plus its current quantity.
 *
 * Wraps an Item rather than an ItemStock because the listing is item-driven —
 * an item whose stock row does not exist yet must still be listed, reading as
 * zero. See InventoryRepository::stockLevels() for why.
 *
 * @property Item $resource
 */
class InventoryStockResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Larastan types the HasOne access as non-nullable; at runtime the
        // relation is genuinely null until the item's first movement.
        /** @var ItemStock|null $stock */
        $stock = $this->resource->stock;

        $quantityOnHand = $stock instanceof ItemStock ? (string) $stock->quantity_on_hand : '0.0000';
        $reorderLevel = (string) $this->resource->reorder_level;

        return [
            'item' => new ItemSummaryResource($this->resource),
            'quantity_on_hand' => $quantityOnHand,
            'reorder_level' => $reorderLevel,
            'is_low_stock' => bccomp($quantityOnHand, $reorderLevel, 4) <= 0,
            'updated_at' => $stock?->updated_at?->toIso8601String(),
        ];
    }
}
