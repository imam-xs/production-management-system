<?php

namespace App\Http\Resources;

use App\Models\ProductionConsumptionModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ProductionConsumptionModel $resource
 */
class ProductionConsumptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'quantity_consumed' => (string) $this->resource->quantity_consumed,
            'input_batch' => new BatchResource($this->whenLoaded('inputBatch')),
        ];
    }
}
