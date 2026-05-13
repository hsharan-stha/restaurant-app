<?php

namespace App\Models;

use App\Enums\PrintLogStatus;
use App\Enums\PrintLogType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintLog extends Model
{
    protected $fillable = [
        'order_id',
        'printer_id',
        'print_type',
        'status',
        'message',
        'order_item_ids',
        'bytes_sent',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'print_type' => PrintLogType::class,
            'status' => PrintLogStatus::class,
            'order_item_ids' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class);
    }
}
