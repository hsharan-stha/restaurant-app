@extends('layouts.app')

@section('title', 'Checkout')

@section('content')
    <div class="mb-6">
        <a href="{{ route('orders.show', $order) }}" class="text-sm text-slate-400 hover:text-white">Back to order</a>
    </div>

    <h1 class="text-2xl font-semibold text-white">Checkout</h1>
    <p class="mt-1 text-slate-400">Order #{{ $order->id }} | Total due <span class="text-emerald-300">${{ number_format($order->invoice->total, 2) }}</span></p>

    <form method="POST" action="{{ route('payments.store', $order) }}" class="mt-8 max-w-md space-y-4">
        @csrf
        <input type="hidden" name="method" value="cash">

        <div class="rounded-xl border border-slate-800 bg-slate-900 px-4 py-4">
            <p class="text-sm font-medium text-white">Payment method</p>
            <p class="mt-2 text-sm text-slate-400">Cash only. Completing checkout will mark this order as paid.</p>
        </div>

        <button type="submit" class="w-full rounded-lg bg-violet-600 py-2.5 text-sm font-medium text-white hover:bg-violet-500">Complete payment</button>
    </form>
@endsection
