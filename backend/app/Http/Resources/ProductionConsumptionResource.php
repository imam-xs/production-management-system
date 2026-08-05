<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionConsumptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'quantity_consumed' => (string) $this->resource->quantity_consumed,
            'input_batch' => new BatchResource($this->whenLoaded('inputBatch')),
        ];
    }
}
