<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Preparing = 'preparing';
    case Completed = 'completed';
}
