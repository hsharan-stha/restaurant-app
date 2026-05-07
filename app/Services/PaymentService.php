<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TableStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

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
