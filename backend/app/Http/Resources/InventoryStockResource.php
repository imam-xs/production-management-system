<?php

namespace App\Http\Resources;

use App\Models\ItemStockModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// wraps an item, not a stock row: an item with no stock row yet still has to
// appear, reading as zero
class InventoryStockResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        // null until the item's first movement
        $stock = $this->resource->stock;

        $quantityOnHand = $stock instanceof ItemStockModel ? (string) $stock->quantity_on_hand : '0.0000';
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
