<?php

namespace App\Http\Requests\Admin;

use App\Enums\PrinterConnectionType;
use App\Enums\PrinterRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePrinterRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'connection_type' => ['required', Rule::enum(PrinterConnectionType::class)],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required_if:connection_type,'.PrinterConnectionType::NetworkEscpos->value, 'nullable', 'integer', 'min:1', 'max:65535'],
            'paper_width' => ['required', Rule::in(['58', '80'])],
            'role' => ['required', Rule::enum(PrinterRole::class)],
            'is_enabled' => ['sometimes', 'boolean'],
            'auto_print_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
