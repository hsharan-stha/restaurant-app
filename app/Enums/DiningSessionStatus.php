<?php

namespace App\Enums;

enum DiningSessionStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case FoodDelivered = 'food_delivered';
    case Completed = 'completed';
    /** Legacy value for backward compatibility with existing rows. */
    case CheckedOut = 'checked_out';
    case Cancelled = 'cancelled';
}
