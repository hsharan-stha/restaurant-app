<?php

namespace App\Models;

use App\Enums\PrinterConnectionType;
use App\Enums\PrinterRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Printer extends Model
{
    protected $fillable = [
        'name',
        'connection_type',
        'host',
        'port',
        'paper_width',
        'role',
        'is_enabled',
        'auto_print_enabled',
    ];

    protected function casts(): array
    {
        return [
            'connection_type' => PrinterConnectionType::class,
            'role' => PrinterRole::class,
            'is_enabled' => 'boolean',
            'auto_print_enabled' => 'boolean',
            'port' => 'integer',
        ];
    }

    public function printLogs(): HasMany
    {
        return $this->hasMany(PrintLog::class);
    }
}
