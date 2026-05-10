<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Preparing = 'preparing';
    case Completed = 'completed';
    /** Bill settled / session closed; table may return to available. */
    case CheckoutDone = 'checkout_done';
}
