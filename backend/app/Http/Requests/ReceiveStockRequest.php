<?php

namespace App\Http\Requests;

use App\Enums\ItemType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceiveStockRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Scoped to raw materials at the validation layer, not just in
            // InventoryService — a request for a semi-finished item's id gets
            // a clean 422 instead of reaching the service at all.
            'item_id' => [
                'required',
                'integer',
                Rule::exists('items', 'id')->where('type', ItemType::Raw->value),
            ],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'produced_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
