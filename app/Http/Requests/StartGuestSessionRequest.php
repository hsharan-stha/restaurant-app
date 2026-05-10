<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartGuestSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guest_count' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }
}
