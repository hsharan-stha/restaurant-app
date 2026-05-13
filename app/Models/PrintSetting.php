<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintSetting extends Model
{
    protected $fillable = [
        'auto_print_kitchen',
        'auto_print_cashier',
        'kitchen_printer_id',
        'cashier_printer_id',
    ];

    protected function casts(): array
    {
        return [
            'auto_print_kitchen' => 'boolean',
            'auto_print_cashier' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrFail();
    }

    public function kitchenPrinter(): BelongsTo
    {
        return $this->belongsTo(Printer::class, 'kitchen_printer_id');
    }

    public function cashierPrinter(): BelongsTo
    {
        return $this->belongsTo(Printer::class, 'cashier_printer_id');
    }
}
