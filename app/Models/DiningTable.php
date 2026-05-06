<?php

namespace App\Models;

use App\Enums\TableStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiningTable extends Model
{
    protected $table = 'dining_tables';

    protected $fillable = [
        'table_number',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => TableStatus::class,
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'table_id');
    }
}
