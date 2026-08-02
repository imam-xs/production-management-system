<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for creating an item, shared by all three production stages — the
 * field shape is identical, only the stage differs, and the stage is fixed by
 * which concrete controller is handling the request, not by anything the client
 * sends. One class serves all three for exactly that reason.
 *
 * No authorize() override: every write already requires `auth:sanctum`; this
 * app has no per-resource roles to check beyond that.
 */
class StoreItemRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:64', 'unique:items,sku'],
            'name' => ['required', 'string', 'max:255'],
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'description' => ['nullable', 'string'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
