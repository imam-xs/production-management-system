<?php

namespace App\Http\Resources;

use App\Models\Batch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Batch $resource
 */
class BatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'batch_number' => $this->resource->batch_number,
            'item' => new ItemSummaryResource($this->whenLoaded('item')),
            'quantity_produced' => (string) $this->resource->quantity_produced,
            'quantity_remaining' => (string) $this->resource->quantity_remaining,
            'origin' => $this->resource->origin->value,
            'production_order_number' => $this->resource->productionOrder?->order_number,
            'produced_at' => $this->resource->produced_at->toIso8601String(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
