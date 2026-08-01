<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Listing/filter query shape shared by the three item-type controllers. The
 * repository's own column whitelist (see ItemRepository::SORTABLE) is the
 * actual security boundary against an arbitrary `sort_by`; validating it here
 * too just turns a silently-ignored bad value into a clear 422.
 */
class IndexItemRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'sort_by' => ['nullable', 'string', 'in:name,sku,created_at,reorder_level'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
