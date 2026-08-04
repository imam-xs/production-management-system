<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// the compact shape used when an item is nested inside another response:
// enough to identify it, without ItemResource's stock lookup
class ItemSummaryResource extends JsonResource
{
    /** @return array<string, mixed> */
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
