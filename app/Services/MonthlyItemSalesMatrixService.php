<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Category;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MonthlyItemSalesMatrixService
{
    /**
     * @return array{
     *     year: int,
     *     month: int,
     *     month_label: string,
     *     categories: Collection<int, array{id: int, name: string, items: Collection<int, array{id: int, name: string}>}>,
     *     item_ids: array<int, int>,
     *     date_rows: Collection<int, array{date: string, date_label: string, cells: array<int, array{quantity: int, amount: float}>, row_totals: array{quantity: int, amount: float}}>,
     *     column_totals: array<int, array{quantity: int, amount: float}>,
     *     grand_totals: array{quantity: int, amount: float}
     * }
     */
    public function build(int $year, int $month): array
    {
        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $categories = Category::query()
            ->with(['menuItems' => fn ($q) => $q->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn ($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'items' => $cat->menuItems->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                ]),
            ])
            ->filter(fn (array $c) => $c['items']->isNotEmpty())
            ->values();

        $itemIds = $categories
            ->flatMap(fn (array $c) => $c['items']->pluck('id'))
            ->unique()
            ->values()
            ->map(fn ($id) => (int) $id)
            ->all();

        $aggregates = $this->aggregatesForMonth($monthStart, $monthEnd);

        $dates = $this->datesInMonth($monthStart, $monthEnd);

        $columnTotals = [];
        foreach ($itemIds as $mid) {
            $columnTotals[$mid] = ['quantity' => 0, 'amount' => 0.0];
        }

        $grandQty = 0;
        $grandAmt = 0.0;

        $dateRows = $dates->map(function (Carbon $day) use ($aggregates, $itemIds, &$columnTotals, &$grandQty, &$grandAmt) {
            $key = $day->toDateString();
            $cells = [];
            $rowQty = 0;
            $rowAmt = 0.0;

            foreach ($itemIds as $mid) {
                $entry = $aggregates[$key][$mid] ?? null;
                $q = $entry !== null ? (float) ($entry['quantity'] ?? 0) : 0.0;
                $a = $entry !== null ? (float) ($entry['amount'] ?? 0) : 0.0;

                $qty = (int) round($q);
                $amt = round($a, 2);

                $cells[$mid] = ['quantity' => $qty, 'amount' => $amt];
                $columnTotals[$mid]['quantity'] += $qty;
                $columnTotals[$mid]['amount'] += $amt;
                $rowQty += $qty;
                $rowAmt += $amt;
            }

            $grandQty += $rowQty;
            $grandAmt += $rowAmt;

            return [
                'date' => $key,
                'date_label' => $day->format('Y-m-d'),
                'cells' => $cells,
                'row_totals' => [
                    'quantity' => $rowQty,
                    'amount' => round($rowAmt, 2),
                ],
            ];
        });

        foreach ($columnTotals as $k => $tot) {
            $columnTotals[$k] = [
                'quantity' => (int) $tot['quantity'],
                'amount' => round((float) $tot['amount'], 2),
            ];
        }

        return [
            'year' => $year,
            'month' => $month,
            'month_label' => $monthStart->format('F Y'),
            'categories' => $categories,
            'item_ids' => $itemIds,
            'date_rows' => $dateRows,
            'column_totals' => $columnTotals,
            'grand_totals' => [
                'quantity' => (int) $grandQty,
                'amount' => round($grandAmt, 2),
            ],
        ];
    }

    /**
     * @return array<string, array<int, array{quantity: float, amount: float}>>
     */
    protected function aggregatesForMonth(Carbon $monthStart, Carbon $monthEnd): array
    {
        $dateSql = $this->orderDateSqlExpression();

        $qtyExpr = 'SUM(order_items.quantity)';
        $amtExpr = 'SUM(order_items.quantity * order_items.price)';

        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', [
                OrderStatus::Completed->value,
                OrderStatus::CheckoutDone->value,
            ])
            ->whereBetween('orders.ordered_at', [$monthStart, $monthEnd])
            ->groupBy(DB::raw($dateSql), 'order_items.menu_item_id')
            ->select([
                DB::raw("{$dateSql} as sale_day"),
                'order_items.menu_item_id',
                DB::raw("{$qtyExpr} as qty"),
                DB::raw("{$amtExpr} as amt"),
            ])
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $day = (string) $row->sale_day;
            $mid = (int) $row->menu_item_id;
            if (! isset($out[$day])) {
                $out[$day] = [];
            }
            $out[$day][$mid] = [
                'quantity' => (float) $row->qty,
                'amount' => (float) $row->amt,
            ];
        }

        return $out;
    }

    protected function orderDateSqlExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m-%d', orders.ordered_at)",
            'pgsql' => '(orders.ordered_at::date)',
            default => 'DATE(orders.ordered_at)',
        };
    }

    /**
     * @return Collection<int, Carbon>
     */
    protected function datesInMonth(Carbon $monthStart, Carbon $monthEnd): Collection
    {
        $dates = collect();
        for ($d = $monthStart->copy(); $d->lte($monthEnd); $d->addDay()) {
            $dates->push($d->copy());
        }

        return $dates;
    }
}
