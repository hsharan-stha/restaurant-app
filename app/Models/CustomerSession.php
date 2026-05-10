<?php

namespace App\Models;

use App\Enums\SessionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerSession extends Model
{
    protected $fillable = [
        'table_id',
        'session_token',
        'guest_name',
        'party_size',
        'started_at',
        'last_seen_at',
        'closed_at',
        'status',
        'total_bill',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'closed_at' => 'datetime',
            'status' => SessionStatus::class,
            'total_bill' => 'decimal:2',
        ];
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'table_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
