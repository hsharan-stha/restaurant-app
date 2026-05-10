@php
    use App\Services\MonthlyItemSalesMatrixService;
    /** @var array $report */
@endphp

@extends('layouts.app')

@section('title', 'Item sales matrix')

@push('head')
    <style>
        @media print {
            .sales-matrix-actions { display: none !important; }
            .sales-matrix-wrapper { max-height: none !important; overflow: visible !important; }
            body { background: white !important; color: black !important; }
        }
    </style>
@endpush

@section('content')
    <div class="report-panel mb-2 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-base font-semibold text-slate-100">Item sales matrix</h1>
            <p class="text-[11px] text-slate-500">{{ $report['month_label'] }} · <span class="text-slate-400">{{ $report['value_label'] }}</span></p>
        </div>
        <div class="sales-matrix-actions flex flex-wrap gap-1.5">
            <a href="{{ route('reporting.completed-orders') }}" class="rounded border border-slate-600 bg-slate-900 px-2 py-1 text-[11px] font-medium text-slate-300 hover:bg-slate-800">Orders</a>
            <a href="{{ route('reporting.monthly-item-sales-matrix.csv', ['year' => $report['year'], 'month' => $report['month'], 'mode' => $report['mode']]) }}" class="rounded border border-slate-500/60 bg-slate-800 px-2 py-1 text-[11px] font-medium text-slate-200 hover:bg-slate-700">CSV</a>
            <a href="{{ route('reporting.monthly-item-sales-matrix.pdf', ['year' => $report['year'], 'month' => $report['month'], 'mode' => $report['mode']]) }}" target="_blank" rel="noopener" class="rounded border border-slate-500/60 bg-slate-800 px-2 py-1 text-[11px] font-medium text-slate-200 hover:bg-slate-700">PDF</a>
            <button type="button" onclick="window.print()" class="rounded border border-slate-600 bg-slate-900 px-2 py-1 text-[11px] font-medium text-slate-300 hover:bg-slate-800">Print</button>
        </div>
    </div>

    <div class="report-panel mb-2 rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2">
        <form method="GET" action="{{ route('reporting.monthly-item-sales-matrix') }}" class="flex flex-wrap items-end gap-2">
            <label class="block">
                <span class="mb-0.5 block text-[10px] font-semibold uppercase text-slate-500">Year</span>
                <select name="year" class="rounded border border-slate-700 bg-slate-950 px-2 py-1 text-xs text-white focus:border-slate-500 focus:outline-none">
                    @foreach($years as $y)
                        <option value="{{ $y }}" @selected((int) $report['year'] === $y)>{{ $y }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="mb-0.5 block text-[10px] font-semibold uppercase text-slate-500">Month</span>
                <select name="month" class="rounded border border-slate-700 bg-slate-950 px-2 py-1 text-xs text-white focus:border-slate-500 focus:outline-none">
                    @foreach($months as $num => $label)
                        <option value="{{ $num }}" @selected((int) $report['month'] === $num)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <fieldset class="flex flex-wrap items-center gap-x-3 gap-y-1 border-0 pb-px">
                <span class="w-full text-[10px] font-semibold uppercase text-slate-500 sm:w-auto">Mode</span>
                <label class="flex cursor-pointer items-center gap-1 text-[11px] text-slate-300">
                    <input type="radio" name="mode" value="{{ MonthlyItemSalesMatrixService::MODE_QUANTITY }}" @checked($report['mode'] === MonthlyItemSalesMatrixService::MODE_QUANTITY) class="accent-slate-400">
                    Qty
                </label>
                <label class="flex cursor-pointer items-center gap-1 text-[11px] text-slate-300">
                    <input type="radio" name="mode" value="{{ MonthlyItemSalesMatrixService::MODE_AMOUNT }}" @checked($report['mode'] === MonthlyItemSalesMatrixService::MODE_AMOUNT) class="accent-slate-400">
                    ¥
                </label>
            </fieldset>
            <button type="submit" class="rounded bg-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-900 hover:bg-white">Apply</button>
        </form>
    </div>

    @if(empty($report['item_ids']))
        <div class="report-panel rounded-lg border border-slate-800 py-8 text-center text-xs text-slate-500">
            Define categories with menu items to build columns.
        </div>
    @else
        <div class="sales-matrix-wrapper rounded-md border border-slate-700/70 bg-slate-950/30">
            <div class="sales-matrix-inner overflow-auto max-h-[min(72vh,48rem)] print:max-h-none print:overflow-visible">
                <table class="sales-matrix-table w-max min-w-full border-collapse text-left font-mono text-[11px] tabular-nums leading-tight">
                    <thead>
                        <tr>
                            <th class="sales-matrix-corner sales-matrix-row-head border border-slate-600/90 bg-zinc-800 px-1.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-zinc-200" rowspan="2">Date</th>
                            @foreach($report['categories'] as $idx => $cat)
                                @php $palette = ['sales-matrix-h-cat-a', 'sales-matrix-h-cat-b', 'sales-matrix-h-cat-c']; @endphp
                                <th class="{{ $palette[$idx % 3] }} border border-slate-600/70 px-1 py-1 text-center text-[10px] font-semibold text-zinc-800" colspan="{{ $cat['items']->count() }}">{{ $cat['name'] }}</th>
                            @endforeach
                            <th class="sales-matrix-corner-tr sales-matrix-sticky-last border border-slate-600/90 bg-zinc-500 px-1.5 py-1 text-[10px] font-semibold text-zinc-950" rowspan="2">Σ Day</th>
                        </tr>
                        <tr>
                            @foreach($report['categories'] as $idx => $cat)
                                @foreach($cat['items'] as $item)
                                    @php $pc = [['sales-matrix-h-i-a'], ['sales-matrix-h-i-b'], ['sales-matrix-h-i-c']][$idx % 3][0]; @endphp
                                    <th class="{{ $pc }} sales-matrix-col-head border border-slate-600/70 px-1 py-px text-[10px] font-medium text-zinc-800">{{ $item['name'] }}</th>
                                @endforeach
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($report['date_rows'] as $i => $row)
                            @php $zebra = $i % 2 === 1 ? 'sales-matrix-row-odd' : 'sales-matrix-row-even'; @endphp
                            <tr class="sales-matrix-body-row {{ $zebra }}">
                                <td class="sales-matrix-sticky-col sales-matrix-row-head border border-slate-700/70 bg-inherit px-1.5 py-px font-sans font-medium text-slate-300">{{ $row['date_label'] }}</td>
                                @foreach($report['item_ids'] as $mid)
                                    @php
                                        $cell = $row['cells'][$mid] ?? ($report['mode'] === MonthlyItemSalesMatrixService::MODE_QUANTITY ? 0 : 0);
                                    @endphp
                                    <td class="sales-matrix-num border border-slate-700/60 px-1 py-px text-right font-mono text-slate-300">
                                        @if($report['mode'] === MonthlyItemSalesMatrixService::MODE_QUANTITY)
                                            {{ (int) $cell }}
                                        @else
                                            {{ number_format((float) $cell, 0) }}
                                        @endif
                                    </td>
                                @endforeach
                                <td class="sales-matrix-sticky-last border border-slate-700/70 bg-zinc-800/50 px-1.5 py-px text-right font-semibold text-zinc-200">
                                    @if($report['mode'] === MonthlyItemSalesMatrixService::MODE_QUANTITY)
                                        {{ (int) $row['row_total'] }}
                                    @else
                                        {{ number_format((float) $row['row_total'], 0) }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="sales-matrix-total-row">
                            <td class="sales-matrix-corner sales-matrix-sticky-col sales-matrix-row-head border border-zinc-500 bg-zinc-700 px-1.5 py-1 text-[10px] font-semibold uppercase text-zinc-100">Σ</td>
                            @foreach($report['item_ids'] as $mid)
                                @php $t = $report['column_totals'][$mid] ?? 0; @endphp
                                <td class="border border-zinc-500 bg-zinc-700/95 px-1 py-1 text-right text-[11px] font-semibold text-zinc-50">
                                    @if($report['mode'] === MonthlyItemSalesMatrixService::MODE_QUANTITY)
                                        {{ (int) $t }}
                                    @else
                                        {{ number_format((float) $t, 0) }}
                                    @endif
                                </td>
                            @endforeach
                            <td class="sales-matrix-sticky-last border border-zinc-500 bg-amber-500 px-1.5 py-1 text-right text-[11px] font-bold text-zinc-950">
                                @if($report['mode'] === MonthlyItemSalesMatrixService::MODE_QUANTITY)
                                    {{ (int) $report['grand_total'] }}
                                @else
                                    {{ number_format((float) $report['grand_total'], 0) }}
                                @endif
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <p class="mt-1.5 text-[10px] leading-snug text-slate-500">Completed + checkout done · <code class="rounded bg-slate-900 px-0.5 text-slate-400">ordered_at</code> date roll-up.</p>
    @endif
@endsection
