<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Category;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MonthlyItemSalesMatrixService
{
    public const MODE_QUANTITY = 'quantity';

    public const MODE_AMOUNT = 'amount';

    /**
     * @return array{
     *     year: int,
     *     month: int,
     *     month_label: string,
     *     mode: string,
     *     categories: Collection<int, array{id: int, name: string, items: Collection<int, array{id: int, name: string}>}>,
     *     item_ids: array<int, int>,
     *     date_rows: Collection<int, array{date: string, date_label: string, cells: array<int, float|int>, row_total: float}>,
     *     column_totals: array<int, float|int>,
     *     grand_total: float|int,
     *     value_label: string
     * }
     */
    public function build(int $year, int $month, string $mode): array
    {
        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $mode = $mode === self::MODE_AMOUNT ? self::MODE_AMOUNT : self::MODE_QUANTITY;

        $categories = Category::query()
            ->with(['menuItems' => fn ($q) => $q->orderBy('name')])
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

        $aggregates = $this->aggregatesForMonth($monthStart, $monthEnd, $mode);

        $dates = $this->datesInMonth($monthStart, $monthEnd);

        $columnTotals = array_fill_keys($itemIds, 0);
        $grandTotal = 0.0;

        $dateRows = $dates->map(function (Carbon $day) use ($aggregates, $itemIds, $mode, &$columnTotals, &$grandTotal) {
            $key = $day->toDateString();
            $cells = [];
            $rowTotal = 0.0;

            foreach ($itemIds as $mid) {
                $v = (float) ($aggregates[$key][$mid] ?? 0);
                if ($mode === self::MODE_QUANTITY) {
                    $v = (int) round($v);
                } else {
                    $v = round($v, 2);
                }
                $cells[$mid] = $v;
                $columnTotals[$mid] = ($columnTotals[$mid] ?? 0) + $v;
                $rowTotal += $v;
            }

            if ($mode === self::MODE_QUANTITY) {
                $rowTotal = (int) round($rowTotal);
            } else {
                $rowTotal = round($rowTotal, 2);
            }
            $grandTotal += $rowTotal;

            return [
                'date' => $key,
                'date_label' => $day->format('Y-m-d'),
                'cells' => $cells,
                'row_total' => $rowTotal,
            ];
        });

        if ($mode === self::MODE_QUANTITY) {
            $grandTotal = (int) round($grandTotal);
            foreach ($columnTotals as $k => $v) {
                $columnTotals[$k] = (int) round((float) $v);
            }
        } else {
            $grandTotal = round($grandTotal, 2);
            foreach ($columnTotals as $k => $v) {
                $columnTotals[$k] = round((float) $v, 2);
            }
        }

        return [
            'year' => $year,
            'month' => $month,
            'month_label' => $monthStart->format('F Y'),
            'mode' => $mode,
            'categories' => $categories,
            'item_ids' => $itemIds,
            'date_rows' => $dateRows,
            'column_totals' => $columnTotals,
            'grand_total' => $grandTotal,
            'value_label' => $mode === self::MODE_QUANTITY ? 'Quantity' : 'Sales (¥)',
        ];
    }

    /**
     * @return array<string, array<int, float>> [dateString => [menu_item_id => value]]
     */
    protected function aggregatesForMonth(Carbon $monthStart, Carbon $monthEnd, string $mode): array
    {
        $dateSql = $this->orderDateSqlExpression();

        $qtyExpr = 'SUM(order_items.quantity)';
        $amtExpr = 'SUM(order_items.quantity * order_items.price)';
        $selectValue = $mode === self::MODE_AMOUNT ? "{$amtExpr} as v" : "{$qtyExpr} as v";

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
                DB::raw($selectValue),
            ])
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $day = (string) $row->sale_day;
            $mid = (int) $row->menu_item_id;
            if (! isset($out[$day])) {
                $out[$day] = [];
            }
            $out[$day][$mid] = (float) $row->v;
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
