<?php

namespace App\Enums;

enum PrinterConnectionType: string
{
    case NetworkEscpos = 'network_escpos';
    case FileRaw = 'file_raw';

    public function label(): string
    {
        return match ($this) {
            self::NetworkEscpos => 'Network (ESC/POS, port 9100)',
            self::FileRaw => 'USB / raw device path (server)',
        };
    }
}
