<?php

namespace App\Http\Requests;

use App\Enums\DietaryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'discount_price' => $this->input('discount_price') === '' || $this->input('discount_price') === null
                ? null
                : $this->input('discount_price'),
            'dietary_type' => $this->input('dietary_type') === '' ? null : $this->input('dietary_type'),
            'prep_minutes' => $this->input('prep_minutes') === '' ? null : $this->input('prep_minutes'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'numeric', 'min:0'],
            'discount_price' => ['nullable', 'numeric', 'min:0', 'lte:price'],
            'category_id' => ['required', 'exists:categories,id'],
            'prep_minutes' => ['nullable', 'integer', 'min:0', 'max:32767'],
            'is_bestseller' => ['sometimes', 'boolean'],
            'is_popular' => ['sometimes', 'boolean'],
            'is_available' => ['sometimes', 'boolean'],
            'dietary_type' => ['nullable', Rule::enum(DietaryType::class)],
            'image' => ['nullable', 'image', 'max:4096'],
            'remove_image' => ['sometimes', 'boolean'],
        ];
    }
}
