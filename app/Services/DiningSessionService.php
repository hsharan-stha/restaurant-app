<?php

namespace App\Services;

use App\Enums\DiningSessionStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\DiningSession;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DiningSessionService
{
    public function getOrCreateOpenForTable(int $tableId, ?int $createdBy = null): DiningSession
    {
        return DB::transaction(function () use ($tableId, $createdBy) {
            $existing = DiningSession::query()
                ->where('table_id', $tableId)
                ->whereIn('status', [
                    DiningSessionStatus::Open,
                    DiningSessionStatus::InProgress,
                    DiningSessionStatus::FoodDelivered,
                ])
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($existing) {
                return $existing;
            }

            return DiningSession::query()->create([
                'table_id' => $tableId,
                'session_code' => $this->newSessionCode(),
                'status' => DiningSessionStatus::Open,
                'started_at' => now(),
                'payment_status' => PaymentStatus::Pending,
                'created_by' => $createdBy,
            ]);
        });
    }

    public function syncTotals(DiningSession $session): DiningSession
    {
        $orders = $session->orders()
            ->with('invoice')
            ->get();

        $subtotal = (float) $orders->sum(fn (Order $o) => (float) ($o->invoice->subtotal ?? $o->total_amount));
        $tax = (float) $orders->sum(fn (Order $o) => (float) ($o->invoice->tax ?? 0));
        $grandTotal = (float) $orders->sum(fn (Order $o) => (float) ($o->invoice->total ?? $o->total_amount));

        $session->update([
            'subtotal' => round($subtotal, 2),
            'tax' => round($tax, 2),
            'grand_total' => round($grandTotal, 2),
        ]);

        return $session->fresh();
    }

    public function closeIfNoActiveOrders(DiningSession $session): DiningSession
    {
        $hasActive = $session->orders()
            ->whereIn('status', [
                OrderStatus::Pending->value,
                OrderStatus::Preparing->value,
                OrderStatus::Completed->value,
            ])
            ->exists();

        if ($hasActive) {
            return $session->fresh();
        }

        $session->update([
            'status' => DiningSessionStatus::Completed,
            'closed_at' => now(),
            'payment_status' => PaymentStatus::Completed,
        ]);

        return $this->syncTotals($session->fresh());
    }

    public function syncProgressStatus(DiningSession $session): DiningSession
    {
        $session = $session->fresh(['orders.items']);

        $allItems = $session->orders
            ->flatMap(fn (Order $order) => $order->items);

        if ($allItems->isEmpty()) {
            $next = DiningSessionStatus::Open;
        } else {
            $allDelivered = $allItems->every(
                fn (OrderItem $item) => (int) $item->delivered_quantity >= (int) $item->quantity
            );

            if ($session->payment_status === PaymentStatus::Completed) {
                $next = DiningSessionStatus::Completed;
            } elseif ($allDelivered) {
                $next = DiningSessionStatus::FoodDelivered;
            } else {
                $next = DiningSessionStatus::InProgress;
            }
        }

        if ($session->status !== $next) {
            $session->update([
                'status' => $next,
                'closed_at' => $next === DiningSessionStatus::Completed ? ($session->closed_at ?? now()) : null,
            ]);
        }

        return $session->fresh();
    }

    protected function newSessionCode(): string
    {
        return 'SES-'.strtoupper(Str::padLeft((string) random_int(1, 999999), 6, '0'));
    }
}
