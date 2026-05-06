<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice #{{ $order->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 20px; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        th, td { border-bottom: 1px solid #ddd; padding: 8px; text-align: left; }
        th { color: #666; font-weight: normal; }
        .right { text-align: right; }
        .totals { margin-top: 16px; text-align: right; }
    </style>
</head>
<body>
    <h1>Invoice</h1>
    <p>Order #{{ $order->id }} — Table {{ $order->table->table_number }}</p>
    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>Qty</th>
                <th class="right">Line total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $line)
                <tr>
                    <td>{{ $line->menuItem->name }}</td>
                    <td>{{ $line->quantity }}</td>
                    <td class="right">${{ number_format((float) $line->price * $line->quantity, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="totals">
        <p>Subtotal: ${{ number_format($order->invoice->subtotal, 2) }}</p>
        <p>Tax: ${{ number_format($order->invoice->tax, 2) }}</p>
        <p><strong>Total: ${{ number_format($order->invoice->total, 2) }}</strong></p>
    </div>
</body>
</html>
