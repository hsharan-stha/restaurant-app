<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderItemLineRequest;
use App\Http\Requests\UpdateOrderItemLineRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class PendingOrderItemController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    public function store(StoreOrderItemLineRequest $request, Order $order): JsonResponse
    {
        $order = $this->orderService->addLineToPendingOrder(
            $order,
            (int) $request->validated('menu_item_id'),
            (int) $request->validated('quantity'),
            $request->validated('notes'),
            $request->validated('options') ?? [],
        );

        return response()->json(['order' => $this->orderService->serializeOrderForApi($order)]);
    }

    public function increment(Order $order, OrderItem $orderItem): JsonResponse
    {
        $this->assertItemBelongs($order, $orderItem);
        $order = $this->orderService->incrementOrderItem($orderItem);

        return response()->json(['order' => $this->orderService->serializeOrderForApi($order)]);
    }

    public function decrement(Order $order, OrderItem $orderItem): JsonResponse
    {
        $this->assertItemBelongs($order, $orderItem);
        $order = $this->orderService->decrementOrderItem($orderItem);

        return response()->json(['order' => $this->orderService->serializeOrderForApi($order)]);
    }

    public function destroy(Order $order, OrderItem $orderItem): JsonResponse
    {
        $this->assertItemBelongs($order, $orderItem);
        $order = $this->orderService->removeOrderItem($orderItem);

        return response()->json(['order' => $this->orderService->serializeOrderForApi($order)]);
    }

    public function update(UpdateOrderItemLineRequest $request, Order $order, OrderItem $orderItem): JsonResponse
    {
        $this->assertItemBelongs($order, $orderItem);
        $order = $this->orderService->updatePendingOrderItem(
            $orderItem,
            $request->validated(),
        );

        return response()->json(['order' => $this->orderService->serializeOrderForApi($order)]);
    }

    protected function assertItemBelongs(Order $order, OrderItem $orderItem): void
    {
        abort_unless((int) $orderItem->order_id === (int) $order->id, 404);
    }
}
