<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
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
            ->filter(fn ($o) => in_array($o->status, [
                OrderStatus::Completed,
                OrderStatus::CheckoutDone,
            ], true))
            ->filter(function (Order $order) use ($completedFilterStart, $completedFilterEnd) {
                $anchor = $this->orderReportAnchorAt($order);

                if (! $anchor) {
                    return false;
                }

                return $anchor->between(
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

    public function monthlyItemSalesMatrix(Request $request): View
    {
        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);
        $mode = (string) $request->query('mode', MonthlyItemSalesMatrixService::MODE_QUANTITY);

        $year = max(2000, min(2100, $year));
        $month = max(1, min(12, $month));

        $report = $this->monthlyItemSalesMatrix->build($year, $month, $mode);

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
        $mode = (string) $request->query('mode', MonthlyItemSalesMatrixService::MODE_QUANTITY);

        $report = $this->monthlyItemSalesMatrix->build($year, $month, $mode);
        $filename = sprintf(
            'item-sales-matrix_%d-%02d_%s.csv',
            $report['year'],
            $report['month'],
            $report['mode']
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

            $row1 = ['Date'];
            $row2 = [''];
            foreach ($cats as $cat) {
                $n = $cat['items']->count();
                for ($i = 0; $i < $n; $i++) {
                    $row1[] = $cat['name'];
                }
                foreach ($cat['items'] as $item) {
                    $row2[] = $item['name'];
                }
            }
            $row1[] = 'Daily Total';
            $row2[] = '';

            fputcsv($out, $row1);
            fputcsv($out, $row2);

            foreach ($report['date_rows'] as $dr) {
                $line = [$dr['date_label']];
                foreach ($itemIds as $mid) {
                    $v = $dr['cells'][$mid] ?? ($report['mode'] === MonthlyItemSalesMatrixService::MODE_QUANTITY ? 0 : 0.0);
                    $line[] = $report['mode'] === MonthlyItemSalesMatrixService::MODE_QUANTITY ? (string) $v : number_format((float) $v, 2, '.', '');
                }
                $line[] = $report['mode'] === MonthlyItemSalesMatrixService::MODE_QUANTITY
                    ? (string) $dr['row_total']
                    : number_format((float) $dr['row_total'], 2, '.', '');
                fputcsv($out, $line);
            }

            $totalLine = ['TOTAL'];
            foreach ($itemIds as $mid) {
                $v = $report['column_totals'][$mid] ?? 0;
                $totalLine[] = $report['mode'] === MonthlyItemSalesMatrixService::MODE_QUANTITY
                    ? (string) $v
                    : number_format((float) $v, 2, '.', '');
            }
            $totalLine[] = $report['mode'] === MonthlyItemSalesMatrixService::MODE_QUANTITY
                ? (string) $report['grand_total']
                : number_format((float) $report['grand_total'], 2, '.', '');
            fputcsv($out, $totalLine);

            fclose($out);
        }, $filename, $headers);
    }

    public function monthlyItemSalesMatrixPdf(Request $request): Response
    {
        $year = max(2000, min(2100, (int) $request->query('year', now()->year)));
        $month = max(1, min(12, (int) $request->query('month', now()->month)));
        $mode = (string) $request->query('mode', MonthlyItemSalesMatrixService::MODE_QUANTITY);

        $report = $this->monthlyItemSalesMatrix->build($year, $month, $mode);

        $pdf = Pdf::loadView('reporting.monthly-item-sales-matrix-pdf', compact('report'))
            ->setPaper('a4', 'landscape');

        return $pdf->stream(sprintf(
            'item-sales-matrix_%d-%02d_%s.pdf',
            $report['year'],
            $report['month'],
            $report['mode']
        ));
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
