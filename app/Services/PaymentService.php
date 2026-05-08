<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\TableStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function processLocalPayment(Order $order, PaymentMethod $method): Payment
    {
        return DB::transaction(function () use ($order, $method) {
            $orders = $this->getCheckoutOrders($order);

            if ($orders->isEmpty()) {
                throw new \RuntimeException('There are no unpaid completed orders available for checkout.');
            }

            foreach ($orders as $checkoutOrder) {
                $checkoutOrder->loadMissing('invoice');
                if (! $checkoutOrder->invoice) {
                    throw new \RuntimeException('Invoice must exist before payment.');
                }

                if ($this->hasCompletedPayment($checkoutOrder)) {
                    throw new \RuntimeException('Order is already paid.');
                }
            }

            $payment = null;

            foreach ($orders as $checkoutOrder) {
                $payment = Payment::query()->create([
                    'order_id' => $checkoutOrder->id,
                    'method' => $method,
                    'status' => PaymentStatus::Completed,
                ]);
            }

            $this->releaseTableIfNeeded($order, $orders);

            return $payment;
        });
    }

    protected function hasCompletedPayment(Order $order): bool
    {
        return $order->payments()
            ->where('status', PaymentStatus::Completed)
            ->exists();
    }

    public function getCheckoutOrders(Order $order): Collection
    {
        $baseQuery = Order::query()
            ->with(['invoice', 'payments', 'table', 'items.menuItem'])
            ->where('table_id', $order->table_id)
            ->where('status', OrderStatus::Completed->value);

        if ($order->customer_session_id) {
            $baseQuery->where('customer_session_id', $order->customer_session_id);
        } else {
            $baseQuery->whereKey($order->id);
        }

        return $baseQuery
            ->get()
            ->filter(fn (Order $checkoutOrder) => ! $this->hasCompletedPayment($checkoutOrder))
            ->values();
    }

    protected function releaseTableIfNeeded(Order $order, Collection $paidOrders): void
    {
        $hasRemainingOpenOrders = Order::query()
            ->where('table_id', $order->table_id)
            ->where(function ($query) use ($order, $paidOrders) {
                $query->whereIn('status', [OrderStatus::Pending->value, OrderStatus::Preparing->value])
                    ->orWhere(function ($completedQuery) use ($order, $paidOrders) {
                        $completedQuery->where('status', OrderStatus::Completed->value);

                        if ($order->customer_session_id) {
                            $completedQuery->where('customer_session_id', $order->customer_session_id);
                        }

                        $completedQuery->whereNotIn('id', $paidOrders->pluck('id'));
                    });
            })
            ->exists();

        if (! $hasRemainingOpenOrders) {
            $order->table()->update(['status' => TableStatus::Available]);
        }
    }
}
