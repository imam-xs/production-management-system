<?php

namespace App\Http\Resources;

use App\Models\BillOfMaterial;
use App\Models\ItemStock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One line of a product's recipe: an input item and how much of it a single
 * unit of the output requires.
 *
 * Read-only. Recipes are part of the plant's setup and are seeded, not managed
 * over the API — this endpoint exists so an operator can see what a production
 * run will consume *before* committing to it, rather than discovering the
 * quantities only when stock moves.
 *
 * Carries the input's current stock so the caller can show "needs 250 kg, have
 * 500" without a second round trip.
 *
 * @property BillOfMaterial $resource
 */
class RecipeLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $input = $this->resource->inputItem;

        // Larastan types the HasOne as non-nullable; at runtime the stock row
        // does not exist until the item's first movement.
        /** @var ItemStock|null $stock */
        $stock = $input->stock;

        return [
            'input_item' => new ItemSummaryResource($input),
            'quantity_per_unit' => (string) $this->resource->quantity_per_unit,
            'quantity_on_hand' => $stock instanceof ItemStock ? (string) $stock->quantity_on_hand : '0.0000',
        ];
    }
}
