<?php

namespace App\Http\Requests\Admin;

use App\Enums\PrinterRole;
use App\Models\Printer;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePrintSettingRequest extends FormRequest
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
            'auto_print_kitchen' => ['sometimes', 'boolean'],
            'auto_print_cashier' => ['sometimes', 'boolean'],
            'kitchen_printer_id' => ['nullable', 'integer', 'exists:printers,id'],
            'cashier_printer_id' => ['nullable', 'integer', 'exists:printers,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $kitchenId = $this->input('kitchen_printer_id');
            if ($kitchenId) {
                $p = Printer::query()->find((int) $kitchenId);
                if ($p && $p->role !== PrinterRole::Kitchen) {
                    $validator->errors()->add('kitchen_printer_id', 'Selected printer must have the kitchen role.');
                }
            }
            $cashierId = $this->input('cashier_printer_id');
            if ($cashierId) {
                $p = Printer::query()->find((int) $cashierId);
                if ($p && $p->role !== PrinterRole::Cashier) {
                    $validator->errors()->add('cashier_printer_id', 'Selected printer must have the cashier role.');
                }
            }
        });
    }
}
