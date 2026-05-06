@extends('layouts.app')

@section('title', 'Invoice #'.$order->id)

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <a href="{{ route('orders.show', $order) }}" class="text-sm text-slate-400 hover:text-white">← Order</a>
        <a href="{{ route('invoices.pdf', $order) }}" class="rounded-lg border border-slate-600 px-3 py-1.5 text-sm text-slate-200 hover:bg-slate-800">Download PDF</a>
    </div>

    <div class="mx-auto max-w-2xl rounded-xl border border-slate-800 bg-slate-900/60 p-8">
        <h1 class="text-2xl font-semibold text-white">Invoice</h1>
        <p class="mt-1 text-slate-400">Order #{{ $order->id }} · Table {{ $order->table->table_number }}</p>

        <table class="mt-8 w-full text-sm">
            <thead>
                <tr class="border-b border-slate-800 text-left text-slate-500">
                    <th class="py-2">Item</th>
                    <th class="py-2">Qty</th>
                    <th class="py-2 text-right">Line</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                @foreach($order->items as $line)
                    <tr>
                        <td class="py-2 text-slate-200">{{ $line->menuItem->name }}</td>
                        <td class="py-2 text-slate-400">{{ $line->quantity }}</td>
                        <td class="py-2 text-right text-slate-200">${{ number_format((float) $line->price * $line->quantity, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="mt-6 space-y-1 text-right text-sm">
            <p class="text-slate-400">Subtotal <span class="text-white">${{ number_format($order->invoice->subtotal, 2) }}</span></p>
            <p class="text-slate-400">Tax <span class="text-white">${{ number_format($order->invoice->tax, 2) }}</span></p>
            <p class="text-lg font-semibold text-emerald-300">Total <span>${{ number_format($order->invoice->total, 2) }}</span></p>
        </div>
    </div>
@endsection
