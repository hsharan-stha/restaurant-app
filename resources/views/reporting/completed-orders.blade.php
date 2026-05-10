@extends('layouts.app')

@php
    use App\Enums\OrderStatus;
    use App\Enums\PaymentStatus;
@endphp

@section('title', 'Completed Orders Report')

@section('content')
    <div class="report-panel mb-3 flex flex-wrap items-center justify-between gap-2 border-b border-slate-800/90 pb-2">
        <div>
            <h1 class="text-base font-semibold tracking-tight text-slate-100">Completed orders</h1>
            <p class="text-[11px] text-slate-500">Kitchen completed (<code class="text-slate-400">completed</code>) and paid/closed (<code class="text-slate-400">checkout_done</code>), by settlement or completion date in range.</p>
        </div>
        <a
            href="{{ route('reporting.monthly-item-sales-matrix') }}"
            class="rounded border border-slate-600/80 bg-slate-900 px-2.5 py-1 text-xs font-medium text-slate-200 hover:bg-slate-800"
        >Item sales matrix</a>
    </div>

    <div class="report-panel mb-3 rounded-lg border border-slate-800 bg-slate-900/40 p-3">
        <form method="GET" action="{{ route('reporting.completed-orders') }}" class="flex flex-wrap items-end gap-3">
            <label class="min-w-[9rem] flex-1 sm:max-w-[11rem]">
                <span class="mb-0.5 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">From</span>
                <input
                    type="date"
                    name="completed_from"
                    value="{{ $completedFilterFrom }}"
                    class="w-full rounded border border-slate-700 bg-slate-950 px-2 py-1.5 text-xs text-white focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500/40"
                    required
                >
            </label>
            <label class="min-w-[9rem] flex-1 sm:max-w-[11rem]">
                <span class="mb-0.5 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">To</span>
                <input
                    type="date"
                    name="completed_to"
                    value="{{ $completedFilterTo }}"
                    class="w-full rounded border border-slate-700 bg-slate-950 px-2 py-1.5 text-xs text-white focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500/40"
                    required
                >
            </label>
            <button type="submit" class="rounded bg-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-900 hover:bg-white">
                Apply
            </button>
        </form>
    </div>

    @if($completedOrderGroupsByDate->isEmpty())
        <div class="report-panel rounded-lg border border-slate-800 bg-slate-900/30 py-8 text-center text-xs text-slate-500">
            No completed orders in this range.
        </div>
    @else
        <div class="space-y-2">
            @foreach($completedOrderGroupsByDate as $dateGroup)
                <details class="report-acc group rounded-lg border border-slate-800 bg-slate-900/35" @if($loop->first) open @endif>
                    <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-2 border-b border-slate-800/90 px-2.5 py-2 hover:bg-slate-800/40">
                        <div class="min-w-0">
                            <span class="text-sm font-semibold text-slate-100">{{ $dateGroup['date_label'] }}</span>
                            <span class="ml-2 text-[11px] text-slate-500">{{ $dateGroup['order_count'] }} {{ \Illuminate\Support\Str::plural('order', $dateGroup['order_count']) }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="tabular-nums text-xs font-medium text-slate-300">¥{{ number_format($dateGroup['display_total'], 0) }}</span>
                            <span class="rounded border border-slate-600 px-1.5 py-0.5 text-[10px] text-slate-400 group-open:hidden">+</span>
                            <span class="hidden rounded border border-slate-600 px-1.5 py-0.5 text-[10px] text-slate-400 group-open:inline">−</span>
                        </div>
                    </summary>

                    <div class="divide-y divide-slate-800/80 px-2 py-1">
                        @foreach($dateGroup['groups'] as $group)
                            <div class="report-group py-2 first:pt-1">
                                <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2 text-xs">
                                    <div class="leading-tight">
                                        <span class="font-semibold text-slate-100">Tbl {{ $group['table_number'] }}</span>
                                        <span class="text-slate-500"> · {{ $group['order_count'] }} {{ \Illuminate\Support\Str::plural('order', $group['order_count']) }}</span>
                                        <span class="ml-2 font-mono text-slate-300">¥{{ number_format($group['display_total'], 0) }}</span>
                                        <span class="mt-1 block text-[10px] text-slate-500">
                                            Session {{ $group['session_started_at']?->format('H:i') ?? '—' }}–{{ $group['session_ended_at']?->format('H:i') ?? '—' }}
                                        </span>
                                    </div>
                                </div>
                                <ul class="space-y-1">
                                    @foreach($group['orders'] as $order)
                                        @php
                                            $paid = $order->status === OrderStatus::CheckoutDone
                                                || $order->payments->contains(fn ($payment) => $payment->status === PaymentStatus::Completed);
                                        @endphp
                                        <li class="rounded border border-slate-800/70 bg-slate-950/50 px-2 py-1.5">
                                            <div class="flex items-start justify-between gap-2 text-xs">
                                                <div class="min-w-0">
                                                    <span class="font-medium text-slate-200">#{{ $order->id }}</span>
                                                    <span class="text-[10px] text-slate-500"> · {{ $order->updated_at?->format('M j H:i') ?? '—' }}</span>
                                                    <span class="text-[10px] text-slate-500"> · {{ $order->items->count() }} ln</span>
                                                </div>
                                                <div class="shrink-0 text-right leading-tight">
                                                    <span class="font-mono text-slate-200">¥{{ number_format((float) ($order->invoice->total ?? $order->total_amount), 0) }}</span>
                                                    <span class="block text-[10px] {{ $paid ? 'text-emerald-500/90' : 'text-amber-500/90' }}">{{ $paid ? 'Paid' : 'Unpaid' }}</span>
                                                </div>
                                            </div>
                                            @if($order->items->isNotEmpty())
                                                <ul class="mt-1.5 grid gap-0.5 border-t border-dashed border-slate-800/80 pt-1.5 font-mono text-[10px] text-slate-400">
                                                    @foreach($order->items as $line)
                                                        <li class="flex justify-between gap-2 leading-tight">
                                                            <span class="min-w-0 truncate">{{ $line->menuItem->name }} ×{{ $line->quantity }}</span>
                                                            <span class="shrink-0 tabular-nums">¥{{ number_format((float) $line->price * $line->quantity, 0) }}</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </details>
            @endforeach
        </div>
    @endif
@endsection
