<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderItemLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'options' => ['nullable', 'array'],
            'options.spice_level' => ['nullable', 'string', 'in:mild,medium,hot,extra_hot'],
            'options.toppings' => ['nullable', 'array', 'max:20'],
            'options.toppings.*' => ['string', 'max:120'],
        ];
    }
}
