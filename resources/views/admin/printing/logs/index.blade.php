@extends('layouts.app')

@section('title', 'Print history')

@section('content')
    <div class="mx-auto max-w-[1100px] space-y-4 text-[13px]">
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-800/90 pb-2">
            <div>
                <h1 class="text-base font-semibold text-slate-100">Print history</h1>
                <nav class="mt-2 flex flex-wrap gap-1">
                    <a href="{{ route('admin.printing.printers.index') }}" class="rounded border border-slate-700 bg-slate-900 px-2 py-0.5 text-[11px] text-slate-300 hover:bg-slate-800">Printers</a>
                    <a href="{{ route('admin.printing.settings.edit') }}" class="rounded border border-slate-800 px-2 py-0.5 text-[11px] text-slate-500 hover:text-slate-300">Settings</a>
                </nav>
            </div>
        </header>

        <div class="overflow-x-auto rounded-lg border border-slate-800 bg-slate-900/40">
            <table class="w-full min-w-[720px] border-collapse text-left text-xs">
                <thead class="border-b border-slate-800 bg-slate-950/80 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-2 py-2">When</th>
                        <th class="px-2 py-2">Type</th>
                        <th class="px-2 py-2">Status</th>
                        <th class="px-2 py-2">Order</th>
                        <th class="px-2 py-2">Printer</th>
                        <th class="px-2 py-2">Message</th>
                        <th class="px-2 py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/80">
                    @forelse($logs as $log)
                        <tr class="hover:bg-slate-900/60">
                            <td class="px-2 py-2 text-slate-400">{{ $log->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                            <td class="px-2 py-2 text-slate-300">{{ $log->print_type->value }}</td>
                            <td class="px-2 py-2">
                                <span class="rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase {{ $log->status === \App\Enums\PrintLogStatus::Success ? 'bg-emerald-950/80 text-emerald-300' : ($log->status === \App\Enums\PrintLogStatus::Failed ? 'bg-red-950/70 text-red-200' : 'bg-slate-800 text-slate-300') }}">{{ $log->status->value }}</span>
                            </td>
                            <td class="px-2 py-2 text-slate-300">
                                @if($log->order)
                                    #{{ $log->order->order_number }}
                                    @if($log->order->table)
                                        · T{{ $log->order->table->table_number }}
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-2 py-2 text-slate-400">{{ $log->printer?->name ?? '—' }}</td>
                            <td class="max-w-[200px] truncate px-2 py-2 text-slate-500" title="{{ $log->message }}">{{ $log->message }}</td>
                            <td class="px-2 py-2 text-right">
                                @if($log->status === \App\Enums\PrintLogStatus::Failed && $log->order_id && $log->print_type !== \App\Enums\PrintLogType::Test)
                                    <form action="{{ route('admin.printing.logs.retry', $log) }}" method="post" class="inline">
                                        @csrf
                                        <button type="submit" class="rounded border border-amber-800/70 px-2 py-0.5 text-[11px] text-amber-100 hover:bg-amber-950/50">Retry</button>
                                    </form>
                                @endif
                                @if($log->order_id && $log->print_type !== \App\Enums\PrintLogType::Test && $log->order_item_ids)
                                    <form action="{{ route('admin.printing.logs.reprint', $log) }}" method="post" class="inline" onsubmit="return confirm('Send another physical copy?');">
                                        @csrf
                                        <button type="submit" class="rounded border border-slate-700 px-2 py-0.5 text-[11px] text-slate-300 hover:bg-slate-800">Reprint</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-2 py-8 text-center text-slate-500">No print jobs yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="text-xs text-slate-500">{{ $logs->links() }}</div>
    </div>
@endsection
