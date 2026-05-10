<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\SessionStatus;
use App\Models\Category;
use App\Models\CustomerSession;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderItem;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Services\OrderService;
use App\Support\DashboardFloorVisual;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected OrderRepositoryInterface $orderRepository,
        protected OrderService $orderService,
    ) {}

    public function __invoke(Request $request): View
    {
        return view('dashboard');
    }

    public function floorState(): JsonResponse
    {
        $tables = DiningTable::query()->orderBy('table_number')->get();

        $sessionsByTable = CustomerSession::query()
            ->where('status', SessionStatus::Active->value)
            ->whereIn('table_id', $tables->pluck('id'))
            ->get()
            ->keyBy('table_id');

        $orders = Order::query()
            ->with(['items.menuItem', 'invoice', 'payments'])
            ->whereIn('table_id', $tables->pluck('id'))
            ->orderByDesc('id')
            ->get()
            ->groupBy('table_id');

        $payload = $tables->map(function (DiningTable $table) use ($orders, $sessionsByTable) {
            /** @var Collection<int, Order> $forTable */
            $forTable = $orders->get($table->id, collect());
            $visual = DashboardFloorVisual::forTable($table, $forTable);
            $activeForCounts = $forTable->filter(fn ($o) => $o->status !== OrderStatus::CheckoutDone);

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
                'status' => $table->status->value,
                'visual' => $visual,
                'counts' => [
                    'pending' => $activeForCounts->where('status', OrderStatus::Pending)->count(),
                    'preparing' => $activeForCounts->where('status', OrderStatus::Preparing)->count(),
                    'completed' => $activeForCounts->where('status', OrderStatus::Completed)->count(),
                ],
                'pending_order_ids' => $activeForCounts->where('status', OrderStatus::Pending)->pluck('id')->values()->all(),
                'guest_party_size' => $sessionsByTable->get($table->id)?->party_size,
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

    public function tablePanel(DiningTable $diningTable): JsonResponse
    {
        $orders = Order::query()
            ->where('table_id', $diningTable->id)
            ->with(['items.menuItem', 'invoice', 'payments', 'customerSession'])
            ->orderByDesc('id')
            ->get();

        $sessions = $orders->groupBy(fn ($o) => $o->customer_session_id ?: 'single-'.$o->id);

        return response()->json([
            'table' => [
                'id' => $diningTable->id,
                'table_name' => $diningTable->table_name,
                'table_number' => $diningTable->table_number,
                'status' => $diningTable->status->value,
                'seat_capacity' => $diningTable->seat_capacity,
            ],
            'visual' => DashboardFloorVisual::forTable($diningTable, $orders),
            'active_orders' => $orders
                ->filter(fn ($o) => in_array($o->status, [
                    OrderStatus::Pending,
                    OrderStatus::Preparing,
                    OrderStatus::Completed,
                ], true))
                ->values()
                ->map(fn (Order $o) => $this->serializeOrderForPanel($o)),
            'sessions' => $sessions->map(fn (Collection $group) => [
                'customer_session_id' => $group->first()?->customer_session_id,
                'orders' => $group->map(fn (Order $o) => $this->serializeOrderForPanel($o))->values(),
            ])->values(),
            'menu_catalog' => $this->staffMenuCatalogPayload(),
        ]);
    }

    public function staffMenuCatalog(): JsonResponse
    {
        return response()->json($this->staffMenuCatalogPayload());
    }

    /**
     * @return array{categories: array<int, array<string, mixed>>}
     */
    protected function staffMenuCatalogPayload(): array
    {
        $categories = Category::query()
            ->with(['menuItems' => fn ($q) => $q->orderBy('name')])
            ->orderBy('name')
            ->get();

        return [
            'categories' => $categories->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'items' => $c->menuItems->map(fn ($m) => [
                    'id' => $m->id,
                    'name' => $m->name,
                    'price' => (string) $m->price,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeOrderForPanel(Order $order): array
    {
        return $this->orderService->orderPanelPayload($order);
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
