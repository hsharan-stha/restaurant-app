@php use App\Services\MonthlyItemSalesMatrixService; @endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report['month_label'] }} Item Sales Matrix</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 7pt; color: #0f172a; margin: 12px; }
        h1 { font-size: 12pt; margin: 0 0 8px; }
        .meta { font-size: 8pt; color: #475569; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; page-break-inside: auto; }
        th, td { border: 0.35pt solid #94a3b8; padding: 3px 4px; }
        thead th { background: #e2e8f0; font-weight: bold; text-align: center; }
        tbody td { text-align: right; }
        tbody td:first-child { text-align: left; font-weight: 600; }
        thead tr.cat th { font-size: 7.5pt; }
        thead tr.items th { background: #f8fafc; font-size: 6.8pt; }
        tbody tr:nth-child(even) { background: #f9fafb; }
        .total td { background: #312e81; color: #fff; font-weight: bold; }
        .total td:last-child { background: #d97706; color: #0f172a; }
        .date-col { white-space: nowrap; }
        .muted { font-size: 6.5pt; color: #64748b; margin-top: 8px; }
    </style>
</head>
<body>
    <h1>Monthly Item Sales Matrix</h1>
    <div class="meta">{{ $report['month_label'] }} · {{ $report['value_label'] }}</div>
    @if(empty($report['item_ids']))
        <p>No menu items found.</p>
    @else
        <table>
            <thead>
                <tr class="cat">
                    <th rowspan="2" class="date-col">Date</th>
                    @foreach($report['categories'] as $cat)
                        <th colspan="{{ $cat['items']->count() }}">{{ $cat['name'] }}</th>
                    @endforeach
                    <th rowspan="2">Daily total</th>
                </tr>
                <tr class="items">
                    @foreach($report['categories'] as $cat)
                        @foreach($cat['items'] as $item)
                            <th>{{ $item['name'] }}</th>
                        @endforeach
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($report['date_rows'] as $row)
                    <tr>
                        <td class="date-col">{{ $row['date_label'] }}</td>
                        @foreach($report['item_ids'] as $mid)
                            @php $cell = $row['cells'][$mid] ?? 0; @endphp
                            <td>
                                @if($report['mode'] === MonthlyItemSalesMatrixService::MODE_QUANTITY)
                                    {{ (int) $cell }}
                                @else
                                    ¥{{ number_format((float) $cell, 0, '.', ',') }}
                                @endif
                            </td>
                        @endforeach
                        <td>
                            @if($report['mode'] === MonthlyItemSalesMatrixService::MODE_QUANTITY)
                                {{ (int) $row['row_total'] }}
                            @else
                                ¥{{ number_format((float) $row['row_total'], 0, '.', ',') }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="total">
                    <td class="date-col">TOTAL</td>
                    @foreach($report['item_ids'] as $mid)
                        @php $t = $report['column_totals'][$mid] ?? 0; @endphp
                        <td>
                            @if($report['mode'] === MonthlyItemSalesMatrixService::MODE_QUANTITY)
                                {{ (int) $t }}
                            @else
                                ¥{{ number_format((float) $t, 0, '.', ',') }}
                            @endif
                        </td>
                    @endforeach
                    <td>
                        @if($report['mode'] === MonthlyItemSalesMatrixService::MODE_QUANTITY)
                            {{ (int) $report['grand_total'] }}
                        @else
                            ¥{{ number_format((float) $report['grand_total'], 0, '.', ',') }}
                        @endif
                    </td>
                </tr>
            </tfoot>
        </table>
        <p class="muted">Completed and checkout-done orders only. Values aggregated by calendar day of ordered_at.</p>
    @endif
</body>
</html>
