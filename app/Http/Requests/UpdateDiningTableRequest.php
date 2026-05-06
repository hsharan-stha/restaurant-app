<?php

namespace App\Http\Requests;

use App\Enums\TableStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDiningTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'table_number' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('dining_tables', 'table_number')->ignore($this->route('dining_table')->id),
            ],
            'status' => ['required', Rule::enum(TableStatus::class)],
        ];
    }
}
