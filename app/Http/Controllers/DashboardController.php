<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\OrderItem;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected OrderRepositoryInterface $orderRepository
    ) {}

    public function __invoke(): View
    {
        $orders = $this->orderRepository->allWithRelations();
        $latestCheckoutRequestAt = $orders
            ->filter(fn ($o) => $o->checkout_requested_at)
            ->max(fn ($o) => $o->checkout_requested_at?->toIso8601String());
        $latestOrderItemId = (int) (OrderItem::query()->max('id') ?? 0);

        return view('dashboard', [
            'pendingOrders' => $orders->filter(fn ($o) => $o->status === OrderStatus::Pending)->values(),
            'preparingOrders' => $orders->filter(fn ($o) => $o->status === OrderStatus::Preparing)->values(),
            'completedOrders' => $orders->filter(fn ($o) => $o->status === OrderStatus::Completed)->values(),
            'latestCheckoutRequestAt' => $latestCheckoutRequestAt,
            'latestOrderItemId' => $latestOrderItemId,
        ]);
    }

    public function poll(Request $request): JsonResponse
    {
        $lastSeenId = max(0, (int) $request->query('last_seen_id', 0));
        $lastSeenOrderItemId = max(0, (int) $request->query('last_seen_order_item_id', 0));
        $lastSeenCheckoutAt = $request->query('last_checkout_seen_at');
        $orders = $this->orderRepository->newerThanId($lastSeenId);
        $ordersWithNewItems = OrderItem::query()
            ->with('order.table')
            ->where('id', '>', $lastSeenOrderItemId)
            ->orderBy('id')
            ->get()
            ->pluck('order')
            ->filter()
            ->unique('id')
            ->values();
        $checkoutRequests = $this->orderRepository->checkoutRequestedAfter(
            is_string($lastSeenCheckoutAt) && $lastSeenCheckoutAt !== '' ? $lastSeenCheckoutAt : null
        );

        $announcedOrderIds = [];
        $orderAlerts = collect()
            ->concat($orders)
            ->concat($ordersWithNewItems)
            ->filter(fn ($order) => $order
                && in_array($order->status, [OrderStatus::Pending, OrderStatus::Preparing], true)
                && ! in_array($order->id, $announcedOrderIds, true))
            ->map(function ($order) use (&$announcedOrderIds) {
                $announcedOrderIds[] = $order->id;
                $isPreparingUpdate = $order->status === OrderStatus::Preparing;

                return [
                    'id' => $order->id,
                    'table_number' => $order->table?->table_number,
                    'type' => $isPreparingUpdate ? 'preparing_order_update' : 'new_order',
                    'announcement_text' => sprintf(
                        'Table number %s %s',
                        $order->table?->table_number,
                        $isPreparingUpdate ? 'has added an order' : 'has placed an order'
                    ),
                ];
            })
            ->values();

        return response()->json([
            'orders' => $orderAlerts,
            'max_order_item_id' => (int) (OrderItem::query()->max('id') ?? 0),
            'checkout_requests' => $checkoutRequests->map(fn ($order) => [
                'id' => $order->id,
                'table_number' => $order->table?->table_number,
                'checkout_requested_at' => $order->checkout_requested_at?->toIso8601String(),
                'announcement_text' => sprintf('Table number %s has requested checkout', $order->table?->table_number),
            ])->values(),
        ]);
    }
}
