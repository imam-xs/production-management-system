<?php

namespace App\Http\Requests;

use App\Enums\BatchOrigin;
use App\Enums\ItemType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexBatchRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:64'],
            'item_id' => ['nullable', 'integer', 'exists:items,id'],
            'item_type' => ['nullable', Rule::in(ItemType::values())],
            'origin' => ['nullable', Rule::in(BatchOrigin::values())],
            'available_only' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
