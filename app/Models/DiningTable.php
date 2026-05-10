<?php

namespace App\Models;

use App\Enums\TableStatus;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DiningTable extends Model
{
    protected $table = 'dining_tables';

    protected $fillable = [
        'floor_id',
        'table_number',
        'table_name',
        'shape',
        'x_position',
        'y_position',
        'width',
        'height',
        'scale_x',
        'scale_y',
        'rotation',
        'fill_color',
        'seat_capacity',
        'status',
        'qr_token',
    ];

    protected function casts(): array
    {
        return [
            'status' => TableStatus::class,
            'x_position' => 'decimal:2',
            'y_position' => 'decimal:2',
            'width' => 'decimal:2',
            'height' => 'decimal:2',
            'scale_x' => 'decimal:4',
            'scale_y' => 'decimal:4',
            'rotation' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DiningTable $table) {
            if (! $table->qr_token) {
                $table->qr_token = (string) Str::uuid();
            }

            if ($table->table_number === null) {
                $table->table_number = (int) (static::query()->max('table_number') ?? 0) + 1;
            }

            if ($table->table_name === null || $table->table_name === '') {
                $table->table_name = 'Table '.$table->table_number;
            }
        });
    }

    protected function customerEntryUrl(): Attribute
    {
        return Attribute::get(fn () => route('guest.entry', $this));
    }

    protected function customerQrSvg(): Attribute
    {
        return Attribute::get(function () {
            $options = new QROptions([
                'svgAddXmlHeader' => false,
                'outputBase64' => false,
                'scale' => 6,
            ]);

            return (new QRCode($options))->render($this->customer_entry_url);
        });
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'table_id');
    }

    public function customerSessions(): HasMany
    {
        return $this->hasMany(CustomerSession::class, 'table_id');
    }
}
