<?php

namespace App\Http\Requests;

use App\Enums\TableStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncFloorPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'tables' => ['required', 'array'],
            'tables.*.id' => ['required', 'integer', 'exists:dining_tables,id'],
            'tables.*.table_name' => ['required', 'string', 'max:128'],
            'tables.*.shape' => ['required', Rule::in(['square', 'round'])],
            'tables.*.x_position' => ['required', 'numeric'],
            'tables.*.y_position' => ['required', 'numeric'],
            'tables.*.width' => ['required', 'numeric', 'between:40,800'],
            'tables.*.height' => ['required', 'numeric', 'between:40,800'],
            'tables.*.scale_x' => ['required', 'numeric', 'between:0.25,4'],
            'tables.*.scale_y' => ['required', 'numeric', 'between:0.25,4'],
            'tables.*.rotation' => ['required', 'numeric', 'between:-360,360'],
            'tables.*.fill_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'tables.*.seat_capacity' => ['nullable', 'integer', 'min:1', 'max:99'],
            'tables.*.status' => ['required', Rule::enum(TableStatus::class)],
            'tables.*.floor_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
