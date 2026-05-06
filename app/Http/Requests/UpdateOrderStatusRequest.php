<?php

namespace App\Http\Requests;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'staff']) ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(OrderStatus::class)],
        ];
    }
}
