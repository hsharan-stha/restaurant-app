<?php

namespace App\Http\Requests;

use App\Enums\TableStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFloorTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'shape' => ['required', Rule::in(['square', 'round'])],
            'table_name' => ['nullable', 'string', 'max:128'],
            'x_position' => ['required', 'numeric'],
            'y_position' => ['required', 'numeric'],
            'width' => ['sometimes', 'numeric', 'between:40,800'],
            'height' => ['sometimes', 'numeric', 'between:40,800'],
            'scale_x' => ['sometimes', 'numeric', 'between:0.25,4'],
            'scale_y' => ['sometimes', 'numeric', 'between:0.25,4'],
            'rotation' => ['sometimes', 'numeric', 'between:-360,360'],
            'fill_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'seat_capacity' => ['nullable', 'integer', 'min:1', 'max:99'],
            'status' => ['sometimes', Rule::enum(TableStatus::class)],
            'floor_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $shape = $this->input('shape');
        $defaults = $shape === 'round'
            ? ['width' => 100, 'height' => 100]
            : ['width' => 120, 'height' => 80];

        $merge = [
            'scale_x' => $this->input('scale_x', 1),
            'scale_y' => $this->input('scale_y', 1),
            'rotation' => $this->input('rotation', 0),
            'status' => $this->input('status', TableStatus::Available->value),
        ];

        if (! $this->filled('width')) {
            $merge['width'] = $defaults['width'];
        }

        if (! $this->filled('height')) {
            $merge['height'] = $defaults['height'];
        }

        if (! $this->filled('seat_capacity')) {
            $merge['seat_capacity'] = 4;
        }

        $this->merge($merge);
    }
}
