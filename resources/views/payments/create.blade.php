@extends('layouts.app')

@section('title', 'Checkout')

@section('content')
    <div class="mb-6">
        <a href="{{ route('dashboard') }}" class="text-sm text-slate-400 hover:text-white">Back to dashboard</a>
    </div>

    <h1 class="text-2xl font-semibold text-white">Checkout</h1>
    <p class="mt-1 text-slate-400">
        Table {{ $order->table->table_number }}
        @if($order->customer_session_id)
            | Session checkout
        @else
            | Order #{{ $order->id }}
        @endif
        | Total due <span class="text-emerald-300">&yen;{{ number_format($total, 2) }}</span>
    </p>

    <form method="POST" action="{{ route('payments.store', $order) }}" class="mt-8 max-w-md space-y-4">
        @csrf
        <input type="hidden" name="method" value="cash">

        <div class="rounded-xl border border-slate-800 bg-slate-900 px-4 py-4">
            <p class="text-sm font-medium text-white">Completed orders in this checkout</p>
            <ul class="mt-3 space-y-3 text-sm text-slate-300">
                @foreach($checkoutOrders as $checkoutOrder)
                    <li class="rounded-lg border border-slate-800 bg-slate-950/60 px-3 py-3">
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-medium text-white">Order #{{ $checkoutOrder->id }}</span>
                            <span>&yen;{{ number_format((float) $checkoutOrder->invoice->total, 2) }}</span>
                        </div>
                        @if($checkoutOrder->items->isNotEmpty())
                            <ul class="mt-3 space-y-2 border-t border-slate-800 pt-3 text-slate-400">
                                @foreach($checkoutOrder->items as $line)
                                    <li class="flex items-start justify-between gap-3">
                                        <span class="min-w-0 flex-1">{{ $line->menuItem->name }} x {{ $line->quantity }}</span>
                                        <span class="shrink-0 text-slate-300">&yen;{{ number_format((float) $line->price * $line->quantity, 2) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
            <div class="mt-4 space-y-1 border-t border-slate-800 pt-3 text-sm">
                <div class="flex items-center justify-between text-slate-400">
                    <span>Subtotal</span>
                    <span>&yen;{{ number_format($subtotal, 2) }}</span>
                </div>
                <div class="flex items-center justify-between text-slate-400">
                    <span>Tax</span>
                    <span>&yen;{{ number_format($tax, 2) }}</span>
                </div>
                <div class="flex items-center justify-between font-semibold text-white">
                    <span>Total</span>
                    <span>&yen;{{ number_format($total, 2) }}</span>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-slate-800 bg-slate-900 px-4 py-4">
            <p class="text-sm font-medium text-white">Payment method</p>
            <p class="mt-2 text-sm text-slate-400">Cash only. Completing checkout will mark all listed completed orders as paid.</p>
        </div>

        <button type="submit" class="w-full rounded-lg bg-violet-600 py-2.5 text-sm font-medium text-white hover:bg-violet-500">Complete payment</button>
    </form>
@endsection
