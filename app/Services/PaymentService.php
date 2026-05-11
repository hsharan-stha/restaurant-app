<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(
        protected OrderService $orderService,
        protected DiningSessionService $diningSessionService,
    ) {}

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

                $this->orderService->transitionPaidOrderToCheckoutDone($checkoutOrder->fresh(['invoice', 'items.menuItem']));
            }

            $primarySession = $orders->first()?->diningSession;
            if ($primarySession) {
                $this->diningSessionService->syncTotals($primarySession);
                $this->diningSessionService->closeIfNoActiveOrders($primarySession->fresh());
            }

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
        } elseif ($order->dining_session_id) {
            $baseQuery->where('dining_session_id', $order->dining_session_id);
        } else {
            $baseQuery->whereKey($order->id);
        }

        return $baseQuery
            ->get()
            ->filter(fn (Order $checkoutOrder) => ! $this->hasCompletedPayment($checkoutOrder))
            ->values();
    }
}
