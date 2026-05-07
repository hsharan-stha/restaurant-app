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
        'table_number',
        'status',
        'qr_token',
    ];

    protected static function booted(): void
    {
        static::creating(function (DiningTable $table) {
            if (! $table->qr_token) {
                $table->qr_token = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => TableStatus::class,
        ];
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
