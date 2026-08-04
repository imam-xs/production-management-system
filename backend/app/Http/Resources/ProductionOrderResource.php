<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionOrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'order_number' => $this->resource->order_number,
            'stage' => $this->resource->stage->value,
            'output_item' => new ItemSummaryResource($this->whenLoaded('outputItem')),
            'planned_quantity' => (string) $this->resource->planned_quantity,
            'produced_quantity' => (string) $this->resource->produced_quantity,
            'status' => $this->resource->status->value,
            'failure_reason' => $this->resource->failure_reason,
            'created_by' => $this->resource->creator?->name,
            // Present only on the show/execute response, not on index listings
            // — avoids an eager-load-or-N+1 dilemma on the history endpoint.
            'output_batch' => $this->whenLoaded('outputBatch', fn () => new BatchResource($this->resource->outputBatch)),
            'consumptions' => ProductionConsumptionResource::collection($this->whenLoaded('consumptions')),
            'completed_at' => $this->resource->completed_at?->toIso8601String(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
