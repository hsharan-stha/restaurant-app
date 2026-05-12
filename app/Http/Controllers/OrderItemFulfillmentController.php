<?php

namespace App\Http\Controllers;

use App\Enums\PreparationStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\DashboardPanelService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderItemFulfillmentController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
        protected DashboardPanelService $dashboardPanelService,
    ) {}

    public function markPreparing(Order $order, OrderItem $orderItem): JsonResponse
    {
        $this->assertItemBelongs($order, $orderItem);
        $order = $this->orderService->updateOrderItemPreparationStatus($orderItem, PreparationStatus::Preparing);

        return response()->json([
            'order' => $this->orderService->serializeOrderForApi($order),
            'panel' => $this->dashboardPanelService->tablePanelPayload($order->table()->firstOrFail()),
        ]);
    }

    public function markReady(Order $order, OrderItem $orderItem): JsonResponse
    {
        $this->assertItemBelongs($order, $orderItem);
        $order = $this->orderService->updateOrderItemPreparationStatus($orderItem, PreparationStatus::Ready);

        return response()->json([
            'order' => $this->orderService->serializeOrderForApi($order),
            'panel' => $this->dashboardPanelService->tablePanelPayload($order->table()->firstOrFail()),
        ]);
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

        return response()->json([
            'order' => $this->orderService->serializeOrderForApi($updatedOrder),
            'panel' => $this->dashboardPanelService->tablePanelPayload($updatedOrder->table()->firstOrFail()),
        ]);
    }

    public function deliverAllReady(Request $request, Order $order): JsonResponse
    {
        $updatedOrder = $this->orderService->deliverAllReadyItemsForOrder($order, $request->user()?->id);

        return response()->json([
            'order' => $this->orderService->serializeOrderForApi($updatedOrder),
            'panel' => $this->dashboardPanelService->tablePanelPayload($updatedOrder->table()->firstOrFail()),
        ]);
    }

    protected function assertItemBelongs(Order $order, OrderItem $orderItem): void
    {
        abort_unless((int) $orderItem->order_id === (int) $order->id, 404);
    }
}
