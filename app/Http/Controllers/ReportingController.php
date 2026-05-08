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

        $completedOrderGroups = $this->buildCompletedOrderGroups($completedOrders);

        return view('reporting.completed-orders', [
            'completedOrderGroupsByDate' => $this->buildCompletedOrderGroupsByDate($completedOrderGroups),
            'tableSpendSummaries' => $this->buildTableSpendSummaries($completedOrderGroups),
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
                $customerSession = $group
                    ->map(fn ($order) => $order->customerSession)
                    ->first(fn ($session) => $session !== null);
                $sessionStartedAt = $customerSession?->started_at;
                $sessionEndedAt = $customerSession?->closed_at
                    ?? $group->max(fn ($order) => $order->updated_at ?? $order->created_at);

                return [
                    'id' => $firstOrder->customer_session_id ?: $firstOrder->id,
                    'table_number' => $firstOrder->table?->table_number,
                    'status' => OrderStatus::Completed,
                    'customer_session_id' => $firstOrder->customer_session_id,
                    'completed_at' => $group->max(fn ($order) => $order->updated_at ?? $order->created_at),
                    'session_started_at' => $sessionStartedAt,
                    'session_ended_at' => $sessionEndedAt,
                    'orders' => $group->values(),
                    'order_count' => $group->count(),
                    'display_total' => $group->sum(fn ($order) => (float) ($order->invoice->total ?? $order->total_amount)),
                    'checkout_order' => $checkoutOrder,
                ];
            })
            ->sortByDesc(fn (array $group) => $group['completed_at']?->timestamp ?? 0)
            ->values();
    }

    protected function buildCompletedOrderGroupsByDate(Collection $completedOrderGroups): Collection
    {
        return $completedOrderGroups
            ->groupBy(function (array $group) {
                $completedAt = $group['completed_at'];

                return $completedAt ? $completedAt->toDateString() : 'unknown';
            })
            ->map(function (Collection $groups, string $dateKey) {
                $dayTotal = $groups->sum(fn (array $group) => (float) $group['display_total']);
                $orderCount = $groups->sum(fn (array $group) => (int) $group['order_count']);

                return [
                    'date_key' => $dateKey,
                    'date_label' => $dateKey === 'unknown'
                        ? 'Unknown date'
                        : Carbon::parse($dateKey)->format('M d, Y'),
                    'order_count' => $orderCount,
                    'display_total' => $dayTotal,
                    'groups' => $groups
                        ->sortByDesc(fn (array $group) => $group['completed_at']?->timestamp ?? 0)
                        ->values(),
                ];
            })
            ->sortByDesc(fn (array $day) => $day['date_key'])
            ->values();
    }

    protected function buildTableSpendSummaries(Collection $completedOrderGroups): Collection
    {
        return $completedOrderGroups
            ->groupBy(fn (array $group) => (string) ($group['table_number'] ?? 'Unknown'))
            ->map(function (Collection $groups, string $tableNumber) {
                $customerCount = $groups
                    ->map(fn (array $group) => $group['customer_session_id'] ?: 'order-'.$group['id'])
                    ->unique()
                    ->count();

                return [
                    'table_number' => $tableNumber,
                    'customer_count' => $customerCount,
                    'order_count' => $groups->sum(fn (array $group) => (int) $group['order_count']),
                    'total_spend' => $groups->sum(fn (array $group) => (float) $group['display_total']),
                ];
            })
            ->sortByDesc(fn (array $row) => $row['total_spend'])
            ->values();
    }
}
