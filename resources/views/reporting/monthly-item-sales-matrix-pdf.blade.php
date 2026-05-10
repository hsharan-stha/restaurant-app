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
        th, td { border: 0.35pt solid #94a3b8; padding: 3px 4px; vertical-align: top; }
        thead th { background: #e2e8f0; font-weight: bold; text-align: center; }
        tbody td { text-align: right; }
        tbody td:first-child { text-align: left; font-weight: 600; }
        thead tr.cat th { font-size: 7.5pt; }
        thead tr.items th { background: #f8fafc; font-size: 6.8pt; }
        tbody tr:nth-child(even) { background: #f9fafb; }
        .cell-qty { display: block; font-weight: 600; }
        .cell-amt { display: block; font-size: 6.5pt; color: #475569; margin-top: 1px; }
        .total td { background: #312e81; color: #fff; font-weight: bold; }
        .total td .cell-amt { color: #cbd5f5; }
        .total td:last-child { background: #d97706; color: #0f172a; }
        .total td:last-child .cell-amt { color: #292524; }
        .date-col { white-space: nowrap; vertical-align: top; }
        .muted { font-size: 6.5pt; color: #64748b; margin-top: 8px; }
    </style>
</head>
<body>
    <h1>Monthly Item Sales Matrix</h1>
    <div class="meta">{{ $report['month_label'] }} · Quantity + sales (¥)</div>
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
                    <th rowspan="2">Σ day</th>
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
                            @php $cell = $row['cells'][$mid] ?? ['quantity' => 0, 'amount' => 0]; @endphp
                            <td>
                                <span class="cell-qty">{{ (int) $cell['quantity'] }}</span>
                                <span class="cell-amt">¥{{ number_format((float) $cell['amount'], 0, '.', ',') }}</span>
                            </td>
                        @endforeach
                        <td>
                            <span class="cell-qty">{{ (int) $row['row_totals']['quantity'] }}</span>
                            <span class="cell-amt">¥{{ number_format((float) $row['row_totals']['amount'], 0, '.', ',') }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="total">
                    <td class="date-col">TOTAL</td>
                    @foreach($report['item_ids'] as $mid)
                        @php $t = $report['column_totals'][$mid] ?? ['quantity' => 0, 'amount' => 0]; @endphp
                        <td>
                            <span class="cell-qty">{{ (int) $t['quantity'] }}</span>
                            <span class="cell-amt">¥{{ number_format((float) $t['amount'], 0, '.', ',') }}</span>
                        </td>
                    @endforeach
                    <td>
                        <span class="cell-qty">{{ (int) $report['grand_totals']['quantity'] }}</span>
                        <span class="cell-amt">¥{{ number_format((float) $report['grand_totals']['amount'], 0, '.', ',') }}</span>
                    </td>
                </tr>
            </tfoot>
        </table>
        <p class="muted">Completed and checkout-done orders only. Each cell: qty (top), ¥ (bottom). Aggregated by calendar day of ordered_at.</p>
    @endif
</body>
</html>
