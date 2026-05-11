@php
    $receiptWidthPt = $paper === '58' ? 210 : 270;
    $orderDate = $bill['order_datetime'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Bill {{ $bill['order_reference'] }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 18pt; font-family: DejaVu Sans Mono, monospace; color: #111827; font-size: 12px; }
        .receipt { width: {{ $receiptWidthPt }}pt; margin: 0 auto; padding: 8pt 9pt; border: 1px solid #d1d5db; border-radius: 4pt; }
        .head { text-align: center; margin-bottom: 6pt; }
        .head h1 { margin: 0; font-size: 18px; }
        .muted { color: #4b5563; }
        .rule { border-top: 1px dashed #6b7280; margin: 5pt 0; }
        .row { width: 100%; }
        .row td { padding: 1pt 0; }
        .row td:last-child { text-align: right; }
        table.items { width: 100%; border-collapse: collapse; }
        .items th, .items td { padding: 2pt 0; }
        .items th { text-align: left; border-bottom: 1px dashed #666; }
        .items th:last-child, .items td:last-child { text-align: right; }
        .grand { font-weight: bold; font-size: 14px; }
        .qr { text-align: center; margin-top: 7pt; }
        .qr svg { width: 96pt; height: 96pt; }
    </style>
</head>
<body>
    <article class="receipt">
        <header class="head">
            <h1>{{ $bill['restaurant_name'] }}</h1>
            <div class="muted">THERMAL CUSTOMER BILL</div>
            <div>Table: {{ $bill['table_number'] ?? '—' }}</div>
            <div>Order: {{ $bill['order_reference'] }}</div>
            <div class="muted">{{ $orderDate?->format('Y-m-d H:i') ?? now()->format('Y-m-d H:i') }}</div>
        </header>

        <div class="rule"></div>
        <table class="items">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Qty</th>
                    <th>Price</th>
                </tr>
            </thead>
            <tbody>
                @foreach($bill['items'] as $line)
                    <tr>
                        <td>{{ $line['name'] }}</td>
                        <td>{{ $line['quantity'] }}</td>
                        <td>¥{{ number_format($line['line_total'], 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="rule"></div>
        <table class="row">
            <tr><td>Subtotal</td><td>¥{{ number_format($bill['subtotal'], 0) }}</td></tr>
            <tr><td>Tax</td><td>¥{{ number_format($bill['tax'], 0) }}</td></tr>
            <tr><td>Discount</td><td>-¥{{ number_format($bill['discount'], 0) }}</td></tr>
            <tr class="grand"><td>Grand Total</td><td>¥{{ number_format($bill['grand_total'], 0) }}</td></tr>
        </table>

        <div class="rule"></div>
        <table class="row">
            <tr><td>Payment</td><td>{{ strtoupper($bill['payment_method'] ?? 'n/a') }}</td></tr>
            <tr><td>Cashier</td><td>{{ $bill['cashier_name'] ?? '—' }}</td></tr>
            <tr><td>Orders</td><td>#{{ implode(' / #', $bill['order_ids']) }}</td></tr>
        </table>

        @if($bill['barcode_value'])
            <div class="rule"></div>
            <div class="head">
                <div class="muted">Barcode Ref</div>
                <strong>{{ $bill['barcode_value'] }}</strong>
            </div>
        @endif

        @if($bill['qr_svg'])
            <div class="qr">
                {!! $bill['qr_svg'] !!}
            </div>
        @endif
    </article>
</body>
</html>

