@extends('layouts.app')

@section('title', 'Completed Orders Report')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-white">Completed Orders Report</h1>
    </div>

    <div class="mb-6 rounded-lg border border-slate-800 bg-slate-900/50 p-6">
        <form method="GET" action="{{ route('reporting.completed-orders') }}" class="grid gap-6 sm:grid-cols-3">
            <label class="block">
                <span class="mb-3 block text-sm font-semibold text-slate-300 uppercase tracking-wide">From Date</span>
                <div class="relative">
                    <input
                        type="date"
                        name="completed_from"
                        value="{{ $completedFilterFrom }}"
                        class="w-full rounded-lg border-2 border-slate-700 bg-slate-950 px-4 py-3 text-white text-lg focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-400/20 transition-all duration-200"
                        required
                    >
                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                        <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                </div>
            </label>
            <label class="block">
                <span class="mb-3 block text-sm font-semibold text-slate-300 uppercase tracking-wide">To Date</span>
                <div class="relative">
                    <input
                        type="date"
                        name="completed_to"
                        value="{{ $completedFilterTo }}"
                        class="w-full rounded-lg border-2 border-slate-700 bg-slate-950 px-4 py-3 text-white text-lg focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-400/20 transition-all duration-200"
                        required
                    >
                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                        <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                </div>
            </label>
            <div class="flex items-end">
                <button type="submit" class="w-full rounded-lg bg-emerald-600 px-6 py-3 text-lg font-semibold text-white hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-400/50 transition-all duration-200 shadow-lg hover:shadow-xl">
                    <span class="flex items-center justify-center gap-2">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        Apply Filter
                    </span>
                </button>
            </div>
        </form>
    </div>

    @if($completedOrderGroupsByDate->isEmpty())
        <div class="rounded-lg border border-slate-800 bg-slate-900/50 p-6 text-center">
            <p class="text-slate-400">No completed orders found for the selected date range.</p>
        </div>
    @else
        <div class="space-y-6">
            @foreach($completedOrderGroupsByDate as $dateGroup)
                <details class="group rounded-xl border-2 border-cyan-500/40 bg-cyan-950/25 p-4 shadow-lg" @if($loop->first) open @endif>
                    <summary class="mb-4 flex cursor-pointer list-none flex-wrap items-start justify-between gap-3 border-b border-cyan-500/30 pb-3">
                        <div>
                            <p class="text-2xl font-bold text-white">{{ $dateGroup['date_label'] }}</p>
                            <p class="mt-1 text-sm text-slate-300">
                                {{ $dateGroup['order_count'] }} {{ \Illuminate\Support\Str::plural('order', $dateGroup['order_count']) }}
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <p class="text-lg font-semibold text-cyan-200">
                                Date total: ¥{{ number_format($dateGroup['display_total'], 2) }}
                            </p>
                            <span class="rounded-md border border-cyan-300/30 px-2 py-1 text-xs text-cyan-100 group-open:hidden">Expand</span>
                            <span class="rounded-md border border-cyan-300/30 px-2 py-1 text-xs text-cyan-100 hidden group-open:inline-flex">Collapse</span>
                        </div>
                    </summary>

                    <div class="space-y-4">
                        @foreach($dateGroup['groups'] as $group)
                            <div class="rounded-lg border-2 border-emerald-500/40 bg-emerald-950/30 p-4 hover:border-emerald-500/60 hover:bg-emerald-950/50">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="text-3xl font-bold text-white">Table {{ $group['table_number'] }}</p>
                                        <p class="mt-1 text-sm text-slate-400">
                                            {{ $group['order_count'] }} {{ \Illuminate\Support\Str::plural('order', $group['order_count']) }}
                                        </p>
                                        <p class="mt-1 text-lg text-emerald-300">
                                            Total: ¥{{ number_format($group['display_total'], 2) }}
                                        </p>
                                        <p class="mt-1 text-sm text-cyan-200">
                                            Seated:
                                            {{ $group['session_started_at']?->format('g:i A') ?? 'N/A' }}
                                            ->
                                            {{ $group['session_ended_at']?->format('g:i A') ?? 'N/A' }}
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-4 rounded-lg border-2 border-emerald-400/40 bg-emerald-900/20 p-3">
                                    <p class="mb-3 font-semibold uppercase tracking-wide text-emerald-300">Completed Orders</p>
                                    <ul class="space-y-3">
                                        @foreach($group['orders'] as $order)
                                            @php
                                                $paid = $order->payments->contains(fn ($payment) => $payment->status->value === 'completed');
                                            @endphp
                                            <li class="rounded-md border-2 border-emerald-400/40 bg-emerald-900/20 px-3 py-3">
                                                <div class="flex items-start justify-between gap-2">
                                                    <div class="min-w-0">
                                                        <p class="font-semibold text-white">Order #{{ $order->id }}</p>
                                                        <p class="mt-1 text-sm text-slate-400">
                                                            {{ $order->items->count() }} {{ \Illuminate\Support\Str::plural('item', $order->items->count()) }}
                                                        </p>
                                                        <p class="mt-1 text-xs text-slate-500">
                                                            Completed: {{ $order->updated_at?->format('M d, g:i A') ?? 'N/A' }}
                                                        </p>
                                                    </div>
                                                    <div class="text-right">
                                                        <p class="text-emerald-200">¥{{ number_format((float) ($order->invoice->total ?? $order->total_amount), 2) }}</p>
                                                        <p class="mt-1 text-sm {{ $paid ? 'text-emerald-400' : 'text-amber-400' }}">
                                                            {{ $paid ? '✓ Paid' : '✗ Unpaid' }}
                                                        </p>
                                                    </div>
                                                </div>

                                                @if($order->items->isNotEmpty())
                                                    <ul class="mt-3 space-y-2 border-t-2 border-emerald-400/40 pt-3 text-sm text-slate-300">
                                                        @foreach($order->items as $line)
                                                            <li class="flex items-start justify-between gap-3">
                                                                <span class="min-w-0 flex-1">{{ $line->menuItem->name }} × {{ $line->quantity }}</span>
                                                                <span class="shrink-0 text-slate-400">¥{{ number_format((float) $line->price * $line->quantity, 2) }}</span>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </details>
            @endforeach
        </div>
    @endif
@endsection
