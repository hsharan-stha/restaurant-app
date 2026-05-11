@php
    $paperClass = $paper === '58' ? 'paper-58' : 'paper-80';
    $orderDate = $bill['order_datetime'];
    $paymentLabel = $bill['payment_method'] ? strtoupper($bill['payment_method']) : 'N/A';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bill {{ $bill['order_reference'] }}</title>
    <style>
        :root { color-scheme: only light; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f4f4f5; color: #111827; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
        .screen-actions { position: sticky; top: 0; z-index: 20; display: flex; flex-wrap: wrap; gap: 8px; padding: 10px; background: #0f172a; }
        .screen-actions a, .screen-actions button { border: 1px solid #334155; background: #111827; color: #e2e8f0; border-radius: 6px; font-size: 12px; padding: 6px 10px; text-decoration: none; cursor: pointer; }
        .receipt-wrap { padding: 10px; display: flex; justify-content: center; }
        .receipt { background: #fff; border: 1px solid #d4d4d8; padding: 10px 8px; width: 80mm; font-size: 11px; line-height: 1.25; }
        .paper-58 { width: 58mm; font-size: 10px; }
        .head { text-align: center; margin-bottom: 8px; }
        .head h1 { margin: 0; font-size: 15px; letter-spacing: .4px; }
        .muted { color: #4b5563; }
        .rule { border-top: 1px dashed #4b5563; margin: 6px 0; }
        .row { display: flex; justify-content: space-between; gap: 8px; }
        .items th, .items td { padding: 2px 0; vertical-align: top; }
        .items { width: 100%; border-collapse: collapse; }
        .items th { text-align: left; font-weight: 700; border-bottom: 1px dashed #666; }
        .items th:last-child, .items td:last-child { text-align: right; }
        .totals .row { padding: 1px 0; }
        .grand { font-weight: 700; font-size: 13px; }
        .qr { text-align: center; margin-top: 8px; }
        .qr svg { width: 92px; height: 92px; }
        .foot { text-align: center; margin-top: 8px; font-size: 10px; }
        @media print {
            @page { margin: 0; size: auto; }
            body { background: #fff; }
            .screen-actions { display: none !important; }
            .receipt-wrap { padding: 0; }
            .receipt { border: none; margin: 0 auto; width: 100%; max-width: none; }
            .paper-58 { width: 58mm; }
            .paper-80 { width: 80mm; }
        }
    </style>
</head>
<body>
    <div class="screen-actions">
        <button type="button" onclick="window.print()">Print Bill</button>
        <a href="{{ route('bills.thermal.pdf', ['order' => $order, 'ids' => $orderIdsCsv, 'paper' => $paper]) }}">Download PDF</a>
        <a href="{{ route('bills.thermal', ['order' => $order, 'ids' => $orderIdsCsv, 'paper' => $paper === '80' ? '58' : '80']) }}">Switch to {{ $paper === '80' ? '58mm' : '80mm' }}</a>
        <a href="{{ $returnTo }}">Back</a>
    </div>

    <div class="receipt-wrap">
        <article class="receipt {{ $paperClass }}">
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
            <section class="totals">
                <div class="row"><span>Subtotal</span><span>¥{{ number_format($bill['subtotal'], 0) }}</span></div>
                <div class="row"><span>Tax</span><span>¥{{ number_format($bill['tax'], 0) }}</span></div>
                <div class="row"><span>Discount</span><span>-¥{{ number_format($bill['discount'], 0) }}</span></div>
                <div class="row grand"><span>Grand Total</span><span>¥{{ number_format($bill['grand_total'], 0) }}</span></div>
            </section>

            <div class="rule"></div>
            <div class="row"><span>Payment</span><span>{{ $paymentLabel }}</span></div>
            <div class="row"><span>Cashier</span><span>{{ $bill['cashier_name'] ?? '—' }}</span></div>
            <div class="row"><span>Orders</span><span>#{{ implode(' / #', $bill['order_ids']) }}</span></div>

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
                    <div class="muted" style="word-break: break-all;">{{ $bill['qr_value'] }}</div>
                </div>
            @endif

            <footer class="foot muted">
                Thank you. Please visit again.
            </footer>
        </article>
    </div>

    @if($autoprint)
        <script>
            window.addEventListener('load', () => {
                setTimeout(() => window.print(), 120);
            });
            window.addEventListener('afterprint', () => {
                const target = @json($returnTo);
                if (target) window.location.assign(target);
            });
        </script>
    @endif
</body>
</html>

