<?php

namespace App\Enums;

enum PrinterRole: string
{
    case Kitchen = 'kitchen';
    case Cashier = 'cashier';

    public function label(): string
    {
        return match ($this) {
            self::Kitchen => 'Kitchen',
            self::Cashier => 'Cashier / receipt',
        };
    }
}
