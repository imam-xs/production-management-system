<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
