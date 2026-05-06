<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TableStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;

class PaymentService
{
    public function processLocalPayment(Order $order, PaymentMethod $method): Payment
    {
        return DB::transaction(function () use ($order, $method) {
            $order->loadMissing('invoice');
            if (! $order->invoice) {
                throw new \RuntimeException('Invoice must exist before payment.');
            }

            if ($this->hasCompletedPayment($order)) {
                throw new \RuntimeException('Order is already paid.');
            }

            $payment = Payment::query()->create([
                'order_id' => $order->id,
                'method' => $method,
                'status' => PaymentStatus::Completed,
            ]);

            $this->releaseTableIfNeeded($order);

            return $payment;
        });
    }

    /**
     * @throws ApiErrorException
     */
    public function createStripeCheckoutSession(Order $order): string
    {
        $order->loadMissing('invoice', 'table');
        if (! $order->invoice) {
            throw new \RuntimeException('Invoice must exist before payment.');
        }

        $secret = config('services.stripe.secret');
        if (! $secret) {
            throw new \RuntimeException('Stripe is not configured.');
        }

        Stripe::setApiKey($secret);

        $session = Session::create([
            'mode' => 'payment',
            'success_url' => route('payments.stripe.success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('orders.show', $order),
            'metadata' => [
                'order_id' => (string) $order->id,
            ],
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => 'usd',
                    'unit_amount' => (int) round($order->invoice->total * 100),
                    'product_data' => [
                        'name' => 'Order #'.$order->id.' (Table '.$order->table->table_number.')',
                    ],
                ],
            ]],
        ]);

        Payment::query()->create([
            'order_id' => $order->id,
            'method' => PaymentMethod::Online,
            'status' => PaymentStatus::Pending,
            'stripe_payment_intent_id' => $session->id,
        ]);

        return $session->url;
    }

    public function completeStripeFromSessionId(string $sessionId): void
    {
        $secret = config('services.stripe.secret');
        if (! $secret) {
            throw new \RuntimeException('Stripe is not configured.');
        }

        Stripe::setApiKey($secret);
        $session = Session::retrieve($sessionId);

        if ($session->payment_status !== 'paid') {
            throw new \RuntimeException('Payment not completed.');
        }

        $orderId = (int) ($session->metadata->order_id ?? 0);
        $order = Order::query()->with('invoice')->findOrFail($orderId);

        DB::transaction(function () use ($order, $sessionId) {
            $payment = Payment::query()
                ->where('order_id', $order->id)
                ->where('stripe_payment_intent_id', $sessionId)
                ->first();

            if ($payment) {
                $payment->update(['status' => PaymentStatus::Completed]);
            }

            $this->releaseTableIfNeeded($order->fresh());
        });
    }

    protected function hasCompletedPayment(Order $order): bool
    {
        return $order->payments()
            ->where('status', PaymentStatus::Completed)
            ->exists();
    }

    protected function releaseTableIfNeeded(Order $order): void
    {
        $order->table()->update(['status' => TableStatus::Available]);
    }
}
