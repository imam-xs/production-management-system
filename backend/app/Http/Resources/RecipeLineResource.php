<?php

namespace App\Http\Resources;

use App\Models\ItemStockModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// one recipe line: an input item and how much of it one output unit needs.
// carries the input's current stock so the caller can show "needs 250, have 500"
class RecipeLineResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $input = $this->resource->inputItem;

        // Larastan types the HasOne as non-nullable; at runtime the stock row
        // does not exist until the item's first movement.
        /** @var ItemStockModel|null $stock */
        $stock = $input->stock;

        return [
            'input_item' => new ItemSummaryResource($input),
            'quantity_per_unit' => (string) $this->resource->quantity_per_unit,
            'quantity_on_hand' => $stock instanceof ItemStockModel ? (string) $stock->quantity_on_hand : '0.0000',
        ];
    }
}
