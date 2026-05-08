<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportingController extends Controller
{
    public function __construct(
        protected OrderRepositoryInterface $orderRepository
    ) {}

    public function completedOrders(Request $request): View
    {
        $orders = $this->orderRepository->allWithRelations();
        
        $completedFilterEnd = $this->parseDate(
            $request->query('completed_to'),
            now()
        );
        $completedFilterStart = $this->parseDate(
            $request->query('completed_from'),
            $completedFilterEnd->copy()->subMonth()
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

        return view('reporting.completed-orders', [
            'completedOrderGroups' => $this->buildCompletedOrderGroups($completedOrders),
            'completedFilterFrom' => $completedFilterStart->toDateString(),
            'completedFilterTo' => $completedFilterEnd->toDateString(),
        ]);
    }

    protected function parseDate(mixed $value, Carbon $default): Carbon
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
}
