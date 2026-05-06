@extends('layouts.app')

@section('title', 'Checkout')

@section('content')
    <div class="mb-6">
        <a href="{{ route('orders.show', $order) }}" class="text-sm text-slate-400 hover:text-white">← Order</a>
    </div>

    <h1 class="text-2xl font-semibold text-white">Checkout</h1>
    <p class="mt-1 text-slate-400">Order #{{ $order->id }} — Total due <span class="text-emerald-300">${{ number_format($order->invoice->total, 2) }}</span></p>

    <form method="POST" action="{{ route('payments.store', $order) }}" class="mt-8 max-w-md space-y-4">
        @csrf
        <div>
            <span class="mb-2 block text-sm text-slate-400">Payment method</span>
            <div class="space-y-2">
                <label class="flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-900 px-3 py-2">
                    <input type="radio" name="method" value="cash" class="text-emerald-500" required>
                    <span>Cash</span>
                </label>
                <label class="flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-900 px-3 py-2">
                    <input type="radio" name="method" value="card" class="text-emerald-500">
                    <span>Card (mock)</span>
                </label>
                <label class="flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-900 px-3 py-2">
                    <input type="radio" name="method" value="online" class="text-emerald-500">
                    <span>Online (Stripe)</span>
                </label>
            </div>
        </div>
        <p class="text-xs text-slate-500">Configure <code class="text-slate-400">STRIPE_KEY</code> and <code class="text-slate-400">STRIPE_SECRET</code> for live Checkout.</p>
        <button type="submit" class="w-full rounded-lg bg-violet-600 py-2.5 text-sm font-medium text-white hover:bg-violet-500">Complete payment</button>
    </form>
@endsection
