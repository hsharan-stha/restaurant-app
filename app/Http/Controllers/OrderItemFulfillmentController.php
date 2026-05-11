<?php

namespace App\Http\Controllers;

use App\Enums\PreparationStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderItemFulfillmentController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    public function markPreparing(Order $order, OrderItem $orderItem): JsonResponse
    {
        $this->assertItemBelongs($order, $orderItem);
        $order = $this->orderService->updateOrderItemPreparationStatus($orderItem, PreparationStatus::Preparing);

        return response()->json(['order' => $this->orderService->serializeOrderForApi($order)]);
    }

    public function markReady(Order $order, OrderItem $orderItem): JsonResponse
    {
        $this->assertItemBelongs($order, $orderItem);
        $order = $this->orderService->updateOrderItemPreparationStatus($orderItem, PreparationStatus::Ready);

        return response()->json(['order' => $this->orderService->serializeOrderForApi($order)]);
    }

    public function deliver(Request $request, Order $order, OrderItem $orderItem): JsonResponse
    {
        $this->assertItemBelongs($order, $orderItem);
        $payload = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);
        $updatedOrder = $this->orderService->deliverOrderItem(
            $orderItem,
            (int) $payload['quantity'],
            $request->user()?->id
        );

        return response()->json(['order' => $this->orderService->serializeOrderForApi($updatedOrder)]);
    }

    public function deliverAllReady(Request $request, Order $order): JsonResponse
    {
        $updatedOrder = $this->orderService->deliverAllReadyItemsForOrder($order, $request->user()?->id);

        return response()->json(['order' => $this->orderService->serializeOrderForApi($updatedOrder)]);
    }

    protected function assertItemBelongs(Order $order, OrderItem $orderItem): void
    {
        abort_unless((int) $orderItem->order_id === (int) $order->id, 404);
    }
}
