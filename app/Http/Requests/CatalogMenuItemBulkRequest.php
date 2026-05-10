<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CatalogMenuItemBulkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:activate,deactivate,delete,set_category'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:menu_items,id'],
            'category_id' => ['required_if:action,set_category', 'nullable', 'exists:categories,id'],
        ];
    }
}
