<?php

namespace App\Enums;

enum TableStatus: string
{
    case Available = 'available';
    case Reserved = 'reserved';
    case Occupied = 'occupied';
}
