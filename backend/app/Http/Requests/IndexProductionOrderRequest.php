<?php

namespace App\Http\Requests;

use App\Enums\ProductionOrderStatus;
use App\Enums\ProductionStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexProductionOrderRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:32'],
            'stage' => ['nullable', Rule::in(ProductionStage::values())],
            'status' => ['nullable', Rule::in(ProductionOrderStatus::values())],
            'output_item_id' => ['nullable', 'integer', 'exists:items,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
