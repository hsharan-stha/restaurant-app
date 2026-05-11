@extends('layouts.app')

@section('title', 'Completed Orders Report')

@section('content')
    <div class="report-panel mb-3 flex flex-wrap items-center justify-between gap-2 border-b border-slate-800/90 pb-2">
        <div>
            <h1 class="text-base font-semibold tracking-tight text-slate-100">Completed dining sessions</h1>
            <p class="text-[11px] text-slate-500">Session-level checkout report with one final bill per table visit.</p>
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

    @if($completedSessions->isEmpty())
        <div class="report-panel rounded-lg border border-slate-800 bg-slate-900/30 py-8 text-center text-xs text-slate-500">
            No completed sessions in this range.
        </div>
    @else
        <div class="overflow-hidden rounded-lg border border-slate-800 bg-slate-900/35">
            <table class="w-full text-left text-xs">
                <thead class="border-b border-slate-800 bg-slate-950/80 text-[10px] uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-2 py-2">Session Code</th>
                        <th class="px-2 py-2">Table</th>
                        <th class="px-2 py-2 text-right">Total Orders</th>
                        <th class="px-2 py-2 text-right">Grand Total</th>
                        <th class="px-2 py-2">Payment</th>
                        <th class="px-2 py-2">Checkout Time</th>
                        <th class="px-2 py-2 text-right">Print Bill</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/80">
                    @foreach($completedSessions as $session)
                        <tr class="text-slate-200">
                            <td class="px-2 py-2 font-medium">{{ $session['session_code'] }}</td>
                            <td class="px-2 py-2">T{{ $session['table_number'] ?? '—' }}</td>
                            <td class="px-2 py-2 text-right">{{ $session['order_count'] }}</td>
                            <td class="px-2 py-2 text-right font-mono">¥{{ number_format($session['grand_total'], 0) }}</td>
                            <td class="px-2 py-2 uppercase">{{ $session['payment_method'] ?? '—' }}</td>
                            <td class="px-2 py-2">{{ $session['checkout_time']?->format('Y-m-d H:i') ?? '—' }}</td>
                            <td class="px-2 py-2 text-right">
                                @if($session['primary_order_id'])
                                    <a href="{{ route('bills.thermal', ['order' => $session['primary_order_id'], 'ids' => $session['order_ids_csv'], 'paper' => '80']) }}" target="_blank" rel="noopener" class="rounded border border-slate-700 px-2 py-1 text-[10px] text-slate-300 hover:bg-slate-800">Print</a>
                                    <a href="{{ route('bills.thermal.pdf', ['order' => $session['primary_order_id'], 'ids' => $session['order_ids_csv'], 'paper' => '80']) }}" class="ml-1 rounded border border-slate-700 px-2 py-1 text-[10px] text-slate-300 hover:bg-slate-800">PDF</a>
                                @else
                                    <span class="text-slate-500">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
