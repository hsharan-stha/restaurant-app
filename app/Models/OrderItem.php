<?php

namespace App\Models;

use App\Enums\PreparationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'menu_item_id',
        'quantity',
        'price',
        'notes',
        'options',
        'preparation_status',
        'delivered_quantity',
        'delivered_at',
        'served_by',
        'is_delivered',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'options' => 'array',
            'preparation_status' => PreparationStatus::class,
            'delivered_quantity' => 'integer',
            'delivered_at' => 'datetime',
            'is_delivered' => 'boolean',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(User::class, 'served_by');
    }
}
