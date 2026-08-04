<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionEventLogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'event_id' => $this->resource->event_id,
            'event_type' => $this->resource->event_type,
            'routing_key' => $this->resource->routing_key,
            'order_number' => $this->resource->productionOrder?->order_number,
            'attempts' => $this->resource->attempts,
            'payload' => $this->resource->payload,
            'occurred_at' => $this->resource->occurred_at->toIso8601String(),
            'processed_at' => $this->resource->processed_at->toIso8601String(),
            // how long the message spent between being published and being
            // handled by the worker visible proof the path is asynchronous
            'lag_seconds' => $this->resource->occurred_at->diffInSeconds($this->resource->processed_at),
        ];
    }
}
