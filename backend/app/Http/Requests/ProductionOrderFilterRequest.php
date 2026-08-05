<?php

namespace App\Http\Requests;

use App\Enums\ProductionOrderStatus;
use App\Enums\ProductionStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductionOrderFilterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'stage' => ['nullable', Rule::in(ProductionStage::values())],
            'status' => ['nullable', Rule::in(ProductionOrderStatus::values())],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
