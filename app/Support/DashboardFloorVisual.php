<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Enums\TableStatus;
use App\Models\DiningTable;
use Illuminate\Support\Collection;

final class DashboardFloorVisual
{
    /**
     * Compute dashboard canvas visual key for a dining table.
     *
     * Priority: pending (blink) → preparing → completed (blue) → table status / available.
     */
    public static function forTable(DiningTable $table, Collection $ordersForTable): string
    {
        $ordersForTable = $ordersForTable->filter(
            fn ($o) => $o->status !== OrderStatus::CheckoutDone
        )->values();

        if ($ordersForTable->contains(fn ($o) => $o->status === OrderStatus::Pending)) {
            return 'pending';
        }

        if ($ordersForTable->contains(fn ($o) => $o->status === OrderStatus::Preparing)) {
            return 'preparing';
        }

        if ($ordersForTable->contains(fn ($o) => $o->status === OrderStatus::Completed)) {
            return 'completed_blue';
        }

        return match ($table->status) {
            TableStatus::Occupied => 'occupied_red',
            TableStatus::Reserved => 'reserved',
            default => 'available',
        };
    }
}
