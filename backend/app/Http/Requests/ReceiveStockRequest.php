<?php

namespace App\Http\Requests;

use App\Enums\ItemType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceiveStockRequest extends FormRequest
{
    public function rules(): array
    {
        return [
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
