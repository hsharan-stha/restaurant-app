<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $method = PaymentMethod::from($request->validated('method'));

        try {
            if ($method === PaymentMethod::Online) {
                if (! config('services.stripe.secret')) {
                    return back()->withErrors(['payment' => 'Stripe is not configured. Use cash or card (mock).']);
                }
                $url = $this->paymentService->createStripeCheckoutSession($order);

                return redirect()->away($url);
            }

            $this->paymentService->processLocalPayment($order, $method);
        } catch (\Throwable $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return redirect()->route('orders.show', $order)->with('status', 'Payment recorded.');
    }

    public function stripeSuccess(Request $request): RedirectResponse
    {
        $sessionId = $request->query('session_id');
        if (! $sessionId) {
            return redirect()->route('dashboard')->withErrors(['payment' => 'Missing session.']);
        }

        try {
            $this->paymentService->completeStripeFromSessionId($sessionId);
        } catch (\Throwable $e) {
            return redirect()->route('dashboard')->withErrors(['payment' => $e->getMessage()]);
        }

        return redirect()->route('dashboard')->with('status', 'Stripe payment completed.');
    }
}
