<?php

namespace App\Http\Requests;

use App\Enums\TableStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDiningTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'table_number' => ['required', 'integer', 'min:1', 'unique:dining_tables,table_number'],
            'status' => ['required', Rule::enum(TableStatus::class)],
        ];
    }
}
