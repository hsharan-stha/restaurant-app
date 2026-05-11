<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Enums\PreparationStatus;
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

        $items = $ordersForTable->flatMap(fn ($order) => $order->items ?? collect());
        if ($items->contains(fn ($item) => $item->preparation_status === PreparationStatus::Pending)) {
            return 'pending';
        }

        if ($items->contains(fn ($item) => $item->preparation_status === PreparationStatus::Preparing)) {
            return 'preparing';
        }

        if ($items->contains(fn ($item) => $item->preparation_status === PreparationStatus::Ready)) {
            return 'completed_blue';
        }

        return match ($table->status) {
            TableStatus::Occupied => 'occupied_red',
            TableStatus::Reserved => 'reserved',
            default => 'available',
        };
    }
}
