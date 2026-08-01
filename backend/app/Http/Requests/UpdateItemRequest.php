<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared validation for updating an item. `type` is deliberately absent from
 * the rules — it is immutable after creation (ItemService::update() strips it
 * even if a client sends it) because changing it would orphan the item's
 * existing batches and bill-of-materials lines.
 */
abstract class UpdateItemRequest extends FormRequest
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

    /**
     * The route's single int parameter, whatever it is named across the three
     * concrete resources (`raw_material`, `semi_finished_product`, ...).
     */
    private function currentItemId(): int
    {
        $params = $this->route()?->parameters() ?? [];

        return (int) reset($params);
    }
}
