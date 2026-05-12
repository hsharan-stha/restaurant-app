<?php

namespace App\Http\Controllers;

use App\Enums\DiningSessionStatus;
use App\Enums\OrderStatus;
use App\Enums\SessionStatus;
use App\Models\CustomerSession;
use App\Models\DiningSession;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderItem;
use App\Enums\PreparationStatus;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Services\DashboardPanelService;
use App\Support\DashboardFloorVisual;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected OrderRepositoryInterface $orderRepository,
        protected DashboardPanelService $dashboardPanelService,
    ) {}

    public function __invoke(Request $request): View
    {
        return view('dashboard');
    }

    public function floorState(): JsonResponse
    {
        $tables = DiningTable::query()->orderBy('table_number')->get();
        $tableIds = $tables->pluck('id');

        $sessionsByTable = CustomerSession::query()
            ->where('status', SessionStatus::Active->value)
            ->whereIn('table_id', $tableIds)
            ->get()
            ->keyBy('table_id');

        $orders = Order::query()
            ->select(['id', 'table_id', 'status'])
            ->with(['items:id,order_id,menu_item_id,preparation_status', 'items.menuItem:id,category_id', 'items.menuItem.category:id,is_kitchen'])
            ->whereIn('table_id', $tableIds)
            ->orderByDesc('id')
            ->get()
            ->groupBy('table_id');
        $openDiningSessionsByTable = DiningSession::query()
            ->whereIn('status', [
                DiningSessionStatus::Open->value,
                DiningSessionStatus::InProgress->value,
                DiningSessionStatus::FoodDelivered->value,
            ])
            ->whereIn('table_id', $tableIds)
            ->get()
            ->keyBy('table_id');

        $payload = $tables->map(function (DiningTable $table) use ($orders, $sessionsByTable, $openDiningSessionsByTable) {
            /** @var Collection<int, Order> $forTable */
            $forTable = $orders->get($table->id, collect());
            $visual = DashboardFloorVisual::forTable($table, $forTable);
            $activeForCounts = $forTable->filter(fn ($o) => $o->status !== OrderStatus::CheckoutDone);
            $activeItems = $activeForCounts->flatMap(fn (Order $order) => $order->items);
            $kitchenItems = $activeItems->filter(fn ($item) => (bool) ($item->menuItem?->category?->is_kitchen ?? false));
            $nonKitchenItems = $activeItems->reject(fn ($item) => (bool) ($item->menuItem?->category?->is_kitchen ?? false));
            $openSession = $openDiningSessionsByTable->get($table->id);
            $hasCompletedAwaitingCheckout = $forTable->contains(fn ($o) => $o->status === OrderStatus::Completed);
            $allPaid = $forTable->isNotEmpty() && $forTable->every(fn ($o) => $o->status === OrderStatus::CheckoutDone);
            $displayStatus = $table->status->value;
            if ($openSession && $openSession->status === DiningSessionStatus::FoodDelivered) {
                $displayStatus = 'checkout_pending';
            } elseif (($allPaid && ! $openSession) || $openSession?->status === DiningSessionStatus::Completed) {
                $displayStatus = 'paid';
            } elseif ($openSession) {
                $displayStatus = 'occupied';
            }

            return [
                'id' => $table->id,
                'floor_id' => $table->floor_id,
                'table_number' => $table->table_number,
                'table_name' => $table->table_name,
                'shape' => $table->shape,
                'x_position' => $table->x_position,
                'y_position' => $table->y_position,
                'width' => $table->width,
                'height' => $table->height,
                'scale_x' => $table->scale_x,
                'scale_y' => $table->scale_y,
                'rotation' => $table->rotation,
                'fill_color' => $table->fill_color,
                'seat_capacity' => $table->seat_capacity,
                'status' => $displayStatus,
                'visual' => $visual,
                'counts' => [
                    'pending' => $activeItems->where('preparation_status', PreparationStatus::Pending)->count(),
                    'pending_kitchen' => $kitchenItems->where('preparation_status', PreparationStatus::Pending)->count(),
                    'pending_non_kitchen' => $nonKitchenItems->where('preparation_status', PreparationStatus::Pending)->count(),
                    'preparing' => $activeItems->where('preparation_status', PreparationStatus::Preparing)->count(),
                    'ready' => $activeItems->where('preparation_status', PreparationStatus::Ready)->count(),
                    'delivered' => $activeItems->where('preparation_status', PreparationStatus::Delivered)->count(),
                ],
                'pending_order_ids' => $activeForCounts->where('status', OrderStatus::Pending)->pluck('id')->values()->all(),
                'guest_party_size' => $sessionsByTable->get($table->id)?->party_size,
                'dining_session' => $openSession ? [
                    'id' => $openSession->id,
                    'code' => $openSession->session_code,
                    'status' => $openSession->status->value,
                    'started_at' => $openSession->started_at?->toIso8601String(),
                    'subtotal' => (string) $openSession->subtotal,
                    'tax' => (string) $openSession->tax,
                    'discount' => (string) $openSession->discount,
                    'grand_total' => (string) $openSession->grand_total,
                ] : null,
            ];
        });

        return response()->json([
            'tables' => $payload->values(),
            'live_order_count' => Order::query()->whereIn('status', [
                OrderStatus::Pending->value,
                OrderStatus::Preparing->value,
                OrderStatus::Completed->value,
            ])->count(),
        ]);
    }

    public function tablePanel(Request $request, DiningTable $diningTable): JsonResponse
    {
        $historyPage = max(1, (int) $request->query('history_page', 1));

        return response()->json($this->dashboardPanelService->tablePanelPayload($diningTable, $historyPage));
    }

    public function staffMenuCatalog(): JsonResponse
    {
        return response()->json($this->dashboardPanelService->staffMenuCatalogPayload());
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
