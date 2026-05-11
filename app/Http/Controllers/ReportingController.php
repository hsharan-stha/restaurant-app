<?php

namespace App\Http\Controllers;

use App\Enums\DiningSessionStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\DiningSession;
use App\Models\Order;
use App\Models\OrderItem;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Services\MonthlyItemSalesMatrixService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse as SymfonyStreamedResponse;

class ReportingController extends Controller
{
    public function __construct(
        protected OrderRepositoryInterface $orderRepository,
        protected MonthlyItemSalesMatrixService $monthlyItemSalesMatrix
    ) {}

    public function completedOrders(Request $request): View
    {
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

        $completedSessions = DiningSession::query()
            ->with(['table', 'orders.invoice', 'orders.payments'])
            ->whereIn('status', [
                DiningSessionStatus::Completed->value,
                DiningSessionStatus::CheckedOut->value,
            ])
            ->whereBetween('closed_at', [
                $completedFilterStart->copy()->startOfDay(),
                $completedFilterEnd->copy()->endOfDay(),
            ])
            ->orderByDesc('closed_at')
            ->get()
            ->map(function (DiningSession $session) {
                $paymentMethod = $session->orders
                    ->flatMap(fn (Order $o) => $o->payments)
                    ->first(fn ($payment) => $payment->status === PaymentStatus::Completed)
                    ?->method
                    ?->value;

                return [
                    'id' => $session->id,
                    'session_code' => $session->session_code,
                    'table_number' => $session->table?->table_number,
                    'order_count' => $session->orders->count(),
                    'grand_total' => (float) $session->grand_total,
                    'payment_method' => $paymentMethod,
                    'checkout_time' => $session->closed_at,
                    'order_ids_csv' => $session->orders->pluck('id')->implode(','),
                    'primary_order_id' => $session->orders->pluck('id')->first(),
                ];
            })
            ->values();

        return view('reporting.completed-orders', [
            'completedSessions' => $completedSessions,
            'completedFilterFrom' => $completedFilterStart->toDateString(),
            'completedFilterTo' => $completedFilterEnd->toDateString(),
        ]);
    }

    public function monthlyItemSalesMatrix(Request $request): View
    {
        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        $year = max(2000, min(2100, $year));
        $month = max(1, min(12, $month));

        $report = $this->monthlyItemSalesMatrix->build($year, $month);

        return view('reporting.monthly-item-sales-matrix', [
            'report' => $report,
            'years' => range(now()->year - 2, now()->year + 1),
            'months' => collect(range(1, 12))->mapWithKeys(fn ($m) => [
                $m => Carbon::create(2024, $m, 1)->format('F'),
            ])->all(),
        ]);
    }

    public function monthlyItemSalesMatrixCsv(Request $request): StreamedResponse|SymfonyStreamedResponse
    {
        $year = max(2000, min(2100, (int) $request->query('year', now()->year)));
        $month = max(1, min(12, (int) $request->query('month', now()->month)));

        $report = $this->monthlyItemSalesMatrix->build($year, $month);
        $filename = sprintf(
            'item-sales-matrix_%d-%02d.csv',
            $report['year'],
            $report['month']
        );

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        return response()->streamDownload(function () use ($report): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");

            $itemIds = $report['item_ids'];
            $cats = $report['categories'];

            $header = ['Date'];
            foreach ($cats as $cat) {
                foreach ($cat['items'] as $item) {
                    $header[] = "{$cat['name']} — {$item['name']} (qty)";
                    $header[] = "{$cat['name']} — {$item['name']} (¥)";
                }
            }
            $header[] = 'Daily total (qty)';
            $header[] = 'Daily total (¥)';
            fputcsv($out, $header);

            foreach ($report['date_rows'] as $dr) {
                $line = [$dr['date_label']];
                foreach ($itemIds as $mid) {
                    $cell = $dr['cells'][$mid] ?? ['quantity' => 0, 'amount' => 0.0];
                    $line[] = (string) $cell['quantity'];
                    $line[] = number_format((float) $cell['amount'], 2, '.', '');
                }
                $line[] = (string) $dr['row_totals']['quantity'];
                $line[] = number_format((float) $dr['row_totals']['amount'], 2, '.', '');
                fputcsv($out, $line);
            }

            $totalLine = ['TOTAL'];
            foreach ($itemIds as $mid) {
                $t = $report['column_totals'][$mid] ?? ['quantity' => 0, 'amount' => 0.0];
                $totalLine[] = (string) $t['quantity'];
                $totalLine[] = number_format((float) $t['amount'], 2, '.', '');
            }
            $totalLine[] = (string) $report['grand_totals']['quantity'];
            $totalLine[] = number_format((float) $report['grand_totals']['amount'], 2, '.', '');
            fputcsv($out, $totalLine);

            fclose($out);
        }, $filename, $headers);
    }

    public function monthlyItemSalesMatrixPdf(Request $request): Response
    {
        $year = max(2000, min(2100, (int) $request->query('year', now()->year)));
        $month = max(1, min(12, (int) $request->query('month', now()->month)));

        $report = $this->monthlyItemSalesMatrix->build($year, $month);

        $pdf = Pdf::loadView('reporting.monthly-item-sales-matrix-pdf', compact('report'))
            ->setPaper('a4', 'landscape');

        return $pdf->stream(sprintf(
            'item-sales-matrix_%d-%02d.pdf',
            $report['year'],
            $report['month']
        ));
    }

    public function deliveryPerformance(Request $request): View
    {
        $to = $this->parseDate($request->query('to'), now());
        $from = $this->parseDate($request->query('from'), $to->copy()->subWeek());
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        $items = OrderItem::query()
            ->with(['menuItem', 'order'])
            ->whereNotNull('delivered_at')
            ->whereBetween('delivered_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get();

        $rows = $items->map(function (OrderItem $item) {
            $orderedAt = $item->order?->ordered_at ?? $item->order?->created_at;
            $preparationMinutes = $orderedAt ? $orderedAt->diffInMinutes($item->delivered_at) : null;

            return [
                'item' => $item->menuItem?->name ?? 'Unknown',
                'table' => $item->order?->table_id,
                'quantity' => (int) $item->quantity,
                'delivered_quantity' => (int) $item->delivered_quantity,
                'prepared_minutes' => $preparationMinutes,
                'delivered_at' => $item->delivered_at,
                'is_delayed' => $preparationMinutes !== null && $preparationMinutes > 30,
            ];
        });

        $avgServingMinutes = round((float) $rows->pluck('prepared_minutes')->filter()->avg(), 1);
        $delayedCount = $rows->where('is_delayed', true)->count();

        return view('reporting.delivery-performance', [
            'rows' => $rows->sortByDesc('delivered_at')->values(),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'avgServingMinutes' => $avgServingMinutes,
            'delayedCount' => $delayedCount,
            'deliveredCount' => $rows->count(),
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

    /**
     * Date used to place an order in the completed-orders report range.
     */
    protected function orderReportAnchorAt(Order $order): ?Carbon
    {
        if ($order->status === OrderStatus::CheckoutDone) {
            return $order->checkout_at
                ?? $order->completed_at
                ?? $order->updated_at
                ?? $order->created_at;
        }

        if ($order->status === OrderStatus::Completed) {
            return $order->completed_at
                ?? $order->updated_at
                ?? $order->created_at;
        }

        return null;
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
                        && ! $order->payments->contains(fn ($payment) => $payment->status === PaymentStatus::Completed)
                );
                $customerSession = $group
                    ->map(fn ($order) => $order->customerSession)
                    ->first(fn ($session) => $session !== null);
                $sessionStartedAt = $customerSession?->started_at;
                $sessionEndedAt = $customerSession?->closed_at
                    ?? $group->max(fn ($order) => $order->updated_at ?? $order->created_at);

                $anchors = $group->map(fn (Order $order) => $this->orderReportAnchorAt($order))->filter();
                $groupAnchor = $anchors->isNotEmpty()
                    ? $anchors->sortByDesc(fn (Carbon $d) => $d->getTimestamp())->first()
                    : null;

                $groupStatus = $group->every(fn (Order $o) => $o->status === OrderStatus::CheckoutDone)
                    ? OrderStatus::CheckoutDone
                    : OrderStatus::Completed;

                return [
                    'id' => $firstOrder->customer_session_id ?: $firstOrder->id,
                    'table_number' => $firstOrder->table?->table_number,
                    'status' => $groupStatus,
                    'customer_session_id' => $firstOrder->customer_session_id,
                    'completed_at' => $groupAnchor,
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
