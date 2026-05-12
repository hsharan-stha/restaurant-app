<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderItemLineRequest;
use App\Http\Requests\UpdateOrderItemLineRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\DashboardPanelService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class PendingOrderItemController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
        protected DashboardPanelService $dashboardPanelService,
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

        return response()->json([
            'order' => $this->orderService->serializeOrderForApi($order),
            'panel' => $this->dashboardPanelService->tablePanelPayload($order->table()->firstOrFail()),
        ]);
    }

    public function increment(Order $order, OrderItem $orderItem): JsonResponse
    {
        $this->assertItemBelongs($order, $orderItem);
        $order = $this->orderService->incrementOrderItem($orderItem);

        return response()->json([
            'order' => $this->orderService->serializeOrderForApi($order),
            'panel' => $this->dashboardPanelService->tablePanelPayload($order->table()->firstOrFail()),
        ]);
    }

    public function decrement(Order $order, OrderItem $orderItem): JsonResponse
    {
        $this->assertItemBelongs($order, $orderItem);
        $order = $this->orderService->decrementOrderItem($orderItem);

        return response()->json([
            'order' => $this->orderService->serializeOrderForApi($order),
            'panel' => $this->dashboardPanelService->tablePanelPayload($order->table()->firstOrFail()),
        ]);
    }

    public function destroy(Order $order, OrderItem $orderItem): JsonResponse
    {
        $this->assertItemBelongs($order, $orderItem);
        $order = $this->orderService->removeOrderItem($orderItem);

        return response()->json([
            'order' => $this->orderService->serializeOrderForApi($order),
            'panel' => $this->dashboardPanelService->tablePanelPayload($order->table()->firstOrFail()),
        ]);
    }

    public function update(UpdateOrderItemLineRequest $request, Order $order, OrderItem $orderItem): JsonResponse
    {
        $this->assertItemBelongs($order, $orderItem);
        $order = $this->orderService->updatePendingOrderItem(
            $orderItem,
            $request->validated(),
        );

        return response()->json([
            'order' => $this->orderService->serializeOrderForApi($order),
            'panel' => $this->dashboardPanelService->tablePanelPayload($order->table()->firstOrFail()),
        ]);
    }

    protected function assertItemBelongs(Order $order, OrderItem $orderItem): void
    {
        abort_unless((int) $orderItem->order_id === (int) $order->id, 404);
    }
}
