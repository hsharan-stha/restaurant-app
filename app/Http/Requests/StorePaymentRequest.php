<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'staff']) ?? false;
    }

    public function rules(): array
    {
        return [
            'method' => ['required', Rule::enum(PaymentMethod::class)],
        ];
    }
}
