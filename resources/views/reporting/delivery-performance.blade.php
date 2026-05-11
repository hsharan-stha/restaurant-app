@extends('layouts.app')

@section('title', 'Delivery Performance')

@section('content')
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2 border-b border-slate-800/90 pb-2">
        <div>
            <h1 class="text-base font-semibold tracking-tight text-slate-100">Food delivery performance</h1>
            <p class="text-[11px] text-slate-500">Kitchen prep and serving timing report by delivered items.</p>
        </div>
    </div>

    <div class="mb-3 rounded-lg border border-slate-800 bg-slate-900/40 p-3">
        <form method="GET" action="{{ route('reporting.delivery-performance') }}" class="flex flex-wrap items-end gap-3">
            <label class="min-w-[9rem] flex-1 sm:max-w-[11rem]">
                <span class="mb-0.5 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">From</span>
                <input type="date" name="from" value="{{ $from }}" class="w-full rounded border border-slate-700 bg-slate-950 px-2 py-1.5 text-xs text-white" required>
            </label>
            <label class="min-w-[9rem] flex-1 sm:max-w-[11rem]">
                <span class="mb-0.5 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">To</span>
                <input type="date" name="to" value="{{ $to }}" class="w-full rounded border border-slate-700 bg-slate-950 px-2 py-1.5 text-xs text-white" required>
            </label>
            <button type="submit" class="rounded bg-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-900 hover:bg-white">Apply</button>
        </form>
    </div>

    <div class="mb-3 grid grid-cols-1 gap-2 sm:grid-cols-3">
        <div class="rounded-lg border border-slate-800 bg-slate-900/30 p-3 text-xs">
            <p class="text-slate-500">Delivered items</p>
            <p class="mt-1 text-xl font-bold text-slate-100">{{ $deliveredCount }}</p>
        </div>
        <div class="rounded-lg border border-slate-800 bg-slate-900/30 p-3 text-xs">
            <p class="text-slate-500">Average serving time</p>
            <p class="mt-1 text-xl font-bold text-slate-100">{{ $avgServingMinutes }} min</p>
        </div>
        <div class="rounded-lg border border-slate-800 bg-slate-900/30 p-3 text-xs">
            <p class="text-slate-500">Delayed items (&gt; 30 min)</p>
            <p class="mt-1 text-xl font-bold text-rose-300">{{ $delayedCount }}</p>
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-800 bg-slate-900/35">
        <table class="w-full text-left text-xs">
            <thead class="border-b border-slate-800 bg-slate-950/80 text-[10px] uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-2 py-2">Item</th>
                    <th class="px-2 py-2">Table</th>
                    <th class="px-2 py-2 text-right">Qty</th>
                    <th class="px-2 py-2 text-right">Delivered</th>
                    <th class="px-2 py-2 text-right">Serving Time</th>
                    <th class="px-2 py-2">Delivered At</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/80">
                @forelse($rows as $row)
                    <tr class="text-slate-200">
                        <td class="px-2 py-2 font-medium">{{ $row['item'] }}</td>
                        <td class="px-2 py-2">{{ $row['table'] ?? '—' }}</td>
                        <td class="px-2 py-2 text-right">{{ $row['quantity'] }}</td>
                        <td class="px-2 py-2 text-right">{{ $row['delivered_quantity'] }}</td>
                        <td class="px-2 py-2 text-right {{ $row['is_delayed'] ? 'text-rose-300' : '' }}">{{ $row['prepared_minutes'] ?? '—' }} min</td>
                        <td class="px-2 py-2">{{ $row['delivered_at']?->format('Y-m-d H:i') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-3 py-6 text-center text-slate-500">No delivered items in this date range.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
