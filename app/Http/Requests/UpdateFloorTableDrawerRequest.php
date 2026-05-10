<?php

namespace App\Http\Requests;

use App\Enums\TableStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFloorTableDrawerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'table_name' => ['sometimes', 'string', 'max:128'],
            'seat_capacity' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:99'],
            'status' => ['sometimes', Rule::enum(TableStatus::class)],
            'fill_color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }
}
