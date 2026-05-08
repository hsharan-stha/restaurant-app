<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\OrderItem;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected OrderRepositoryInterface $orderRepository
    ) {}

    public function __invoke(Request $request): View
    {
        $orders = $this->orderRepository->allWithRelations();
        $completedFilterEnd = $this->parseCompletedDate(
            $request->query('completed_to'),
            now()
        );
        $completedFilterStart = $this->parseCompletedDate(
            $request->query('completed_from'),
            $completedFilterEnd->copy()->subDay()
        );

        if ($completedFilterStart->gt($completedFilterEnd)) {
            [$completedFilterStart, $completedFilterEnd] = [$completedFilterEnd, $completedFilterStart];
        }

        $completedOrders = $orders
            ->filter(fn ($o) => $o->status === OrderStatus::Completed)
            ->filter(function ($order) use ($completedFilterStart, $completedFilterEnd) {
                $completedAt = $order->updated_at ?? $order->created_at;

                if (! $completedAt) {
                    return false;
                }

                return $completedAt->between(
                    $completedFilterStart->copy()->startOfDay(),
                    $completedFilterEnd->copy()->endOfDay()
                );
            })
            ->values();
        $latestCheckoutRequestAt = $orders
            ->filter(fn ($o) => $o->checkout_requested_at)
            ->max(fn ($o) => $o->checkout_requested_at?->toIso8601String());
        $latestOrderItemId = (int) (OrderItem::query()->max('id') ?? 0);

        return view('dashboard', [
            'pendingOrders' => $orders->filter(fn ($o) => $o->status === OrderStatus::Pending)->values(),
            'preparingOrders' => $orders->filter(fn ($o) => $o->status === OrderStatus::Preparing)->values(),
            'completedOrderGroups' => $this->buildCompletedOrderGroups($completedOrders),
            'completedFilterFrom' => $completedFilterStart->toDateString(),
            'completedFilterTo' => $completedFilterEnd->toDateString(),
            'latestCheckoutRequestAt' => $latestCheckoutRequestAt,
            'latestOrderItemId' => $latestOrderItemId,
        ]);
    }

    protected function parseCompletedDate(mixed $value, Carbon $default): Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return $default->copy();
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return $default->copy();
        }
    }

    protected function buildCompletedOrderGroups(Collection $completedOrders): Collection
    {
        return $completedOrders
            ->groupBy(fn ($order) => $order->customer_session_id
                ? 'session-'.$order->customer_session_id
                : 'order-'.$order->id)
            ->map(function (Collection $group) {
                $firstOrder = $group->first();
                $checkoutOrder = $group->first(
                    fn ($order) => $order->invoice
                        && ! $order->payments->contains(fn ($payment) => $payment->status->value === 'completed')
                );

                return [
                    'id' => $firstOrder->customer_session_id ?: $firstOrder->id,
                    'table_number' => $firstOrder->table?->table_number,
                    'status' => OrderStatus::Completed,
                    'customer_session_id' => $firstOrder->customer_session_id,
                    'orders' => $group->values(),
                    'order_count' => $group->count(),
                    'display_total' => $group->sum(fn ($order) => (float) ($order->invoice->total ?? $order->total_amount)),
                    'checkout_order' => $checkoutOrder,
                ];
            })
            ->values();
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
