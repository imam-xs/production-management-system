<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateItemRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sku' => ['sometimes', 'string', 'max:64', Rule::unique('items', 'sku')->ignore($this->currentItemId())],
            'name' => ['sometimes', 'string', 'max:255'],
            'unit_id' => ['sometimes', 'integer', 'exists:units,id'],
            'description' => ['nullable', 'string'],
            'reorder_level' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    // the id being updated, read by position: the three item routes each name the parameter differently
    private function currentItemId(): int
    {
        $params = $this->route()?->parameters() ?? [];

        return (int) reset($params);
    }
}
