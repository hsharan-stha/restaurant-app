<?php

namespace App\Models;

use App\Enums\DiningSessionStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiningSession extends Model
{
    protected $fillable = [
        'table_id',
        'customer_name',
        'session_code',
        'status',
        'started_at',
        'closed_at',
        'subtotal',
        'tax',
        'discount',
        'grand_total',
        'payment_status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => DiningSessionStatus::class,
            'payment_status' => PaymentStatus::class,
            'started_at' => 'datetime',
            'closed_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'tax' => 'decimal:2',
            'discount' => 'decimal:2',
            'grand_total' => 'decimal:2',
        ];
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'table_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'dining_session_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
