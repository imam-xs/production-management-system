<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Only the output item and quantity are client-supplied — the stage, the
 * inputs, and the required quantities of each are all derived server-side
 * (ProductionStage::forOutputType(), the bill of materials), which is what
 * makes "which raw materials were consumed" a computed fact rather than
 * something the caller could misstate.
 */
class StoreProductionOrderRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'output_item_id' => ['required', 'integer', 'exists:items,id'],
            'planned_quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
