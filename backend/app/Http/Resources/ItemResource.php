<?php

namespace App\Http\Resources;

use App\Models\Item;
use App\Models\ItemStock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Item $resource
 */
class ItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // A brand new item has no item_stocks row yet — the row is created
        // lazily on first movement (see InventoryRepository::stockFor) — so
        // "no stock yet" must read as zero rather than null.
        //
        // Larastan types a HasOne access as non-nullable, which is wrong here:
        // the relation genuinely resolves to null until that first movement.
        // The annotation restores the real type.
        /** @var ItemStock|null $stock */
        $stock = $this->resource->stock;

        $quantityOnHand = $stock instanceof ItemStock
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

    /**
     * Whether deletion would be accepted, so the UI can disable the action
     * rather than offer a click that always fails.
     *
     * Mirrors ItemService::delete(), which stays the authority — this is a hint
     * for the interface, never the enforcement. Reads the `withExists` flags the
     * repository attaches; if a caller loaded the item without them, the answer
     * is a conservative `false` rather than a wrong `true`.
     */
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
