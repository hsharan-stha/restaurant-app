<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    public function create(Order $order): View|RedirectResponse
    {
        $order->load(['invoice', 'table', 'payments']);
        if (! $order->invoice) {
            return redirect()->route('orders.show', $order)
                ->withErrors(['payment' => 'Complete the order before paying (status must be completed).']);
        }

        if ($order->payments()->where('status', \App\Enums\PaymentStatus::Completed)->exists()) {
            return redirect()->route('orders.show', $order)->with('status', 'Already paid.');
        }

        return view('payments.create', compact('order'));
    }

    public function store(StorePaymentRequest $request, Order $order): RedirectResponse
    {
        $order->load('invoice');
        if (! $order->invoice) {
            return back()->withErrors(['payment' => 'Invoice not available yet.']);
        }

        try {
            $this->paymentService->processLocalPayment($order, PaymentMethod::Cash);
        } catch (\Throwable $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return redirect()->route('orders.show', $order)->with('status', 'Payment recorded.');
    }
}
