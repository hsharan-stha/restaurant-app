@extends('layouts.app')

@section('title', 'Order #'.$order->id)

@php
    use App\Enums\OrderStatus;
    use App\Enums\PaymentStatus;
@endphp

@section('content')
    <div class="mb-6">
        <a href="{{ route('dashboard') }}" class="text-sm text-slate-400 hover:text-white">← Back to dashboard</a>
    </div>

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-white">Order #{{ $order->id }}</h1>
            <p class="mt-1 text-slate-400">Table {{ $order->table->table_number }}</p>
        </div>
        @include('partials.status-badge', ['status' => $order->status])
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-4">
            <h2 class="mb-3 text-sm font-semibold uppercase text-slate-500">Line items</h2>
            <ul class="divide-y divide-slate-800">
                @foreach($order->items as $line)
                    <li class="flex justify-between py-2 text-sm">
                        <span>{{ $line->menuItem->name }} × {{ $line->quantity }}</span>
                        <span class="text-slate-300">¥{{ number_format((float) $line->price * $line->quantity, 2) }}</span>
                    </li>
                @endforeach
            </ul>
            <p class="mt-4 text-right text-lg font-semibold text-white">¥{{ number_format($order->total_amount, 2) }}</p>
        </div>

        <div class="space-y-4">
            <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-4">
                <h2 class="mb-3 text-sm font-semibold uppercase text-slate-500">Kitchen</h2>
                @if($order->status === OrderStatus::Pending)
                    <form method="POST" action="{{ route('orders.update-status', $order) }}" class="inline">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="{{ OrderStatus::Preparing->value }}">
                        <button type="submit" class="rounded-lg bg-sky-600 px-4 py-2 text-sm text-white hover:bg-sky-500">Start preparing</button>
                    </form>
                @elseif($order->status === OrderStatus::Preparing)
                    <form method="POST" action="{{ route('orders.update-status', $order) }}" class="inline">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="{{ OrderStatus::Completed->value }}">
                        <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm text-white hover:bg-emerald-500">Mark completed</button>
                    </form>
                @else
                    <p class="text-sm text-slate-400">Order is completed.</p>
                @endif
            </div>

            @if($order->invoice)
                <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-4">
                    <h2 class="mb-2 text-sm font-semibold uppercase text-slate-500">Invoice</h2>
                    <p class="text-sm text-slate-400">Subtotal ¥{{ number_format($order->invoice->subtotal, 2) }}</p>
                    <p class="text-sm text-slate-400">Tax ¥{{ number_format($order->invoice->tax, 2) }}</p>
                    <p class="text-lg font-semibold text-white">Total ¥{{ number_format($order->invoice->total, 2) }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <a href="{{ route('invoices.show', $order) }}" class="text-sm text-emerald-400 hover:underline">View bill</a>
                        <a href="{{ route('invoices.pdf', $order) }}" class="text-sm text-slate-400 hover:underline">Download PDF</a>
                    </div>
                </div>
            @endif

            @php $paid = $order->payments->contains(fn ($p) => $p->status === PaymentStatus::Completed); @endphp
            @if($order->invoice && ! $paid)
                <a href="{{ route('payments.create', $order) }}" class="block w-full rounded-lg bg-violet-600 py-3 text-center text-sm font-medium text-white hover:bg-violet-500">Go to checkout</a>
            @endif
            @if($paid)
                <p class="rounded-lg border border-emerald-500/30 bg-emerald-950/40 px-4 py-3 text-sm text-emerald-200">Payment received. Table can be reseated.</p>
            @endif
        </div>
    </div>
@endsection
