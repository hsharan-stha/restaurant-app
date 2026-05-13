@extends('layouts.app')

@section('title', 'Thermal printers')

@section('content')
    <div class="mx-auto max-w-[1200px] space-y-4 text-[13px] leading-snug">
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-800/90 pb-3">
            <div>
                <h1 class="text-base font-semibold tracking-tight text-slate-100">Thermal printers</h1>
                <p class="text-[11px] text-slate-500">ESC/POS · network or raw USB path on this server</p>
                <nav class="mt-2 flex flex-wrap gap-1">
                    <a href="{{ route('admin.printing.settings.edit') }}" class="rounded border border-slate-700 bg-slate-900 px-2 py-0.5 text-[11px] text-slate-300 hover:bg-slate-800">Print settings</a>
                    <a href="{{ route('admin.printing.logs.index') }}" class="rounded border border-slate-800 px-2 py-0.5 text-[11px] text-slate-500 hover:text-slate-300">Print history</a>
                    <a href="{{ route('dashboard') }}" class="rounded border border-slate-800 px-2 py-0.5 text-[11px] text-slate-500 hover:text-slate-300">Dashboard</a>
                </nav>
            </div>
            <a href="{{ route('admin.printing.printers.create') }}" class="rounded bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500">＋ Add printer</a>
        </header>

        <div class="overflow-hidden rounded-lg border border-slate-800 bg-slate-900/40">
            <table class="w-full border-collapse text-left text-xs">
                <thead class="border-b border-slate-800 bg-slate-950/80 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Name</th>
                        <th class="px-3 py-2">Role</th>
                        <th class="px-3 py-2">Connection</th>
                        <th class="px-3 py-2">Target</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/80">
                    @forelse($printers as $printer)
                        @php($reach = $reachability[$printer->id] ?? ['ok' => false, 'message' => null])
                        <tr class="hover:bg-slate-900/60">
                            <td class="px-3 py-2 font-medium text-slate-200">{{ $printer->name }}</td>
                            <td class="px-3 py-2 text-slate-400">{{ $printer->role->label() }}</td>
                            <td class="px-3 py-2 text-slate-400">{{ $printer->connection_type->label() }}</td>
                            <td class="px-3 py-2 font-mono text-[11px] text-slate-300">
                                {{ $printer->host }}@if($printer->connection_type->value === 'network_escpos'):{{ $printer->port }}@endif
                            </td>
                            <td class="px-3 py-2">
                                <span
                                    data-printer-status="{{ $printer->id }}"
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase {{ $reach['ok'] ? 'bg-emerald-950/80 text-emerald-300' : 'bg-red-950/70 text-red-200' }}"
                                >{{ $reach['ok'] ? 'Reachable' : 'Offline' }}</span>
                            </td>
                            <td class="px-3 py-2 text-right">
                                <div class="flex flex-wrap justify-end gap-1">
                                    <button
                                        type="button"
                                        class="rounded border border-slate-700 px-2 py-0.5 text-[11px] text-slate-300 hover:bg-slate-800"
                                        data-check-printer="{{ route('admin.printing.printers.status', $printer) }}"
                                        data-printer-badge="{{ $printer->id }}"
                                    >Ping</button>
                                    <form action="{{ route('admin.printing.printers.test-print', $printer) }}" method="post" class="inline">
                                        @csrf
                                        <button type="submit" class="rounded border border-amber-800/80 bg-amber-950/40 px-2 py-0.5 text-[11px] text-amber-100 hover:bg-amber-900/50">Test print</button>
                                    </form>
                                    <a href="{{ route('admin.printing.printers.edit', $printer) }}" class="rounded border border-slate-700 px-2 py-0.5 text-[11px] text-slate-300 hover:bg-slate-800">Edit</a>
                                    <form action="{{ route('admin.printing.printers.destroy', $printer) }}" method="post" class="inline" onsubmit="return confirm('Delete this printer?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded border border-red-900/60 px-2 py-0.5 text-[11px] text-red-200 hover:bg-red-950/40">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-8 text-center text-slate-500">No printers yet. Add your kitchen or cashier device to enable auto-print.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @push('scripts')
        <script>
            document.querySelectorAll('[data-check-printer]').forEach((btn) => {
                btn.addEventListener('click', async () => {
                    const url = btn.getAttribute('data-check-printer');
                    const id = btn.getAttribute('data-printer-badge');
                    const badge = document.querySelector(`[data-printer-status="${id}"]`);
                    try {
                        const res = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                        const data = await res.json();
                        if (data.reachable) {
                            badge.className = 'inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase bg-emerald-950/80 text-emerald-300';
                            badge.textContent = 'Reachable';
                        } else {
                            badge.className = 'inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase bg-red-950/70 text-red-200';
                            badge.textContent = 'Offline';
                        }
                    } catch {
                        badge.textContent = 'Error';
                    }
                });
            });
        </script>
    @endpush
@endsection
