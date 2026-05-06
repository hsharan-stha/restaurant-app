<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;

class PaymentApiController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    public function store(StorePaymentRequest $request, Order $order): JsonResponse
    {
        $order->load('invoice');
        if (! $order->invoice) {
            return response()->json(['message' => 'Invoice not available.'], 422);
        }

        $method = PaymentMethod::from($request->validated('method'));

        try {
            if ($method === PaymentMethod::Online) {
                if (! config('services.stripe.secret')) {
                    return response()->json(['message' => 'Stripe not configured.'], 422);
                }
                $url = $this->paymentService->createStripeCheckoutSession($order);

                return response()->json(['checkout_url' => $url]);
            }

            $payment = $this->paymentService->processLocalPayment($order, $method);

            return response()->json($payment, 201);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
