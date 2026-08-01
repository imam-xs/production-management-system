<?php

namespace App\Http\Resources;

use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The compact shape used when an item is nested inside a batch or production
 * order response — just enough to identify and label it, not its full
 * inventory state. Keeps those payloads flat instead of recursing into
 * ItemResource's own stock lookup.
 *
 * @property Item $resource
 */
class ItemSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'sku' => $this->resource->sku,
            'name' => $this->resource->name,
            'type' => $this->resource->type->value,
            'unit' => $this->whenLoaded('unit', fn (): string => $this->resource->unit->code),
        ];
    }
}
