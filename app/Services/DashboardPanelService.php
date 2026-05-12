<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Order;
use App\Support\DashboardFloorVisual;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class DashboardPanelService
{
    public function __construct(
        protected OrderService $orderService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function tablePanelPayload(DiningTable $diningTable, int $historyPage = 1, int $historyPerPage = 5): array
    {
        $orders = Order::query()
            ->where('table_id', $diningTable->id)
            ->with(['items.menuItem', 'customerSession', 'diningSession'])
            ->orderByDesc('id')
            ->get();

        $sessions = $orders->groupBy(fn ($o) => $o->dining_session_id ?: $o->customer_session_id ?: 'single-'.$o->id);
        $serializeSession = fn (Collection $group) => [
            'dining_session_id' => $group->first()?->dining_session_id,
            'session_code' => $group->first()?->diningSession?->session_code,
            'session_status' => $group->first()?->diningSession?->status?->value,
            'started_at' => $group->first()?->diningSession?->started_at?->toIso8601String(),
            'subtotal' => (string) ($group->first()?->diningSession?->subtotal ?? 0),
            'tax' => (string) ($group->first()?->diningSession?->tax ?? 0),
            'discount' => (string) ($group->first()?->diningSession?->discount ?? 0),
            'grand_total' => (string) ($group->first()?->diningSession?->grand_total ?? $group->sum('total_amount')),
            'customer_session_id' => $group->first()?->customer_session_id,
            'orders' => $group->map(fn (Order $o) => $this->orderService->orderPanelPayload($o))->values(),
        ];

        $serializedSessions = $sessions->map($serializeSession)->values();

        $historyGroups = $sessions
            ->filter(fn (Collection $group) => $group->isNotEmpty() && $group->every(fn (Order $order) => $order->status === OrderStatus::CheckoutDone))
            ->values();

        $historyPaginator = new LengthAwarePaginator(
            $historyGroups->forPage($historyPage, $historyPerPage)->map($serializeSession)->values()->all(),
            $historyGroups->count(),
            $historyPerPage,
            $historyPage
        );

        return [
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
                ->map(fn (Order $o) => $this->orderService->orderPanelPayload($o)),
            'sessions' => $serializedSessions,
            'session_history' => [
                'data' => $historyPaginator->items(),
                'current_page' => $historyPaginator->currentPage(),
                'last_page' => $historyPaginator->lastPage(),
                'per_page' => $historyPaginator->perPage(),
                'total' => $historyPaginator->total(),
            ],
            'menu_catalog' => $this->staffMenuCatalogPayload(),
        ];
    }

    /**
     * @return array{categories: array<int, array<string, mixed>>}
     */
    public function staffMenuCatalogPayload(): array
    {
        return Cache::remember('dashboard:staff-menu-catalog', now()->addMinutes(5), function () {
            $categories = Category::query()
                ->where('is_active', true)
                ->with(['menuItems' => fn ($q) => $q->orderBy('name')])
                ->orderBy('sort_order')
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
        });
    }
}
