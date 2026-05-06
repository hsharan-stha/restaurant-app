<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'staff']) ?? false;
    }

    public function rules(): array
    {
        return [
            'table_id' => ['required', 'exists:dining_tables,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => ['required', 'exists:menu_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
