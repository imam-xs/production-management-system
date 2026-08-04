<?php

namespace App\Http\Resources;

use App\Models\ItemStockModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        // null until the item's first movement, so "no stock yet" reads as zero
        $stock = $this->resource->stock;

        $quantityOnHand = $stock instanceof ItemStockModel
            ? (string) $stock->quantity_on_hand
            : '0.0000';

        return [
            'id' => $this->resource->id,
            'sku' => $this->resource->sku,
            'name' => $this->resource->name,
            'type' => $this->resource->type->value,
            'unit' => new UnitResource($this->whenLoaded('unit')),
            'description' => $this->resource->description,
            'reorder_level' => (string) $this->resource->reorder_level,
            'quantity_on_hand' => $quantityOnHand,
            'is_low_stock' => bccomp($quantityOnHand, (string) $this->resource->reorder_level, 4) <= 0,
            'is_active' => $this->resource->is_active,
            'can_delete' => $this->canDelete(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }

    // a hint for the UI, never the enforcement: ItemService::delete() decides.
    // reads the repository's withExists flags; missing flags mean false, not true
    private function canDelete(): bool
    {
        foreach (['batches_exists', 'bill_of_materials_exists', 'used_in_exists'] as $flag) {
            $value = $this->resource->getAttribute($flag);

            if ($value === null || (bool) $value === true) {
                return false;
            }
        }

        return true;
    }
}
