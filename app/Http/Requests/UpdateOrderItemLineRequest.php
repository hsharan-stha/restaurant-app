<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderItemLineRequest extends FormRequest
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
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'options' => ['sometimes', 'nullable', 'array'],
            'options.spice_level' => ['nullable', 'string', 'in:mild,medium,hot,extra_hot'],
            'options.toppings' => ['nullable', 'array', 'max:20'],
            'options.toppings.*' => ['string', 'max:120'],
        ];
    }
}
