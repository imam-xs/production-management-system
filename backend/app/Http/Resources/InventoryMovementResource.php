<?php

namespace App\Http\Resources;

use App\Models\InventoryMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property InventoryMovement $resource
 */
class InventoryMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'type' => $this->resource->type->value,
            'quantity' => (string) $this->resource->quantity,
            'balance_after' => (string) $this->resource->balance_after,
            'batch_number' => $this->resource->batch?->batch_number,
            'reference_type' => $this->resource->reference_type,
            'reference_id' => $this->resource->reference_id,
            'note' => $this->resource->note,
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
