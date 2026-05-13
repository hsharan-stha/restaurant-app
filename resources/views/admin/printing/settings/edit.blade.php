@extends('layouts.app')

@section('title', 'Print settings')

@section('content')
    <div class="mx-auto max-w-xl space-y-4 text-[13px]">
        <header class="border-b border-slate-800/90 pb-2">
            <h1 class="text-base font-semibold text-slate-100">Print settings</h1>
            <p class="text-[11px] text-slate-500">Auto-print runs after each new order line batch (guest, API, or staff POS).</p>
            <nav class="mt-2 flex flex-wrap gap-1">
                <a href="{{ route('admin.printing.printers.index') }}" class="rounded border border-slate-700 bg-slate-900 px-2 py-0.5 text-[11px] text-slate-300 hover:bg-slate-800">Printers</a>
                <a href="{{ route('admin.printing.logs.index') }}" class="rounded border border-slate-800 px-2 py-0.5 text-[11px] text-slate-500 hover:text-slate-300">Print history</a>
                <a href="{{ route('dashboard') }}" class="rounded border border-slate-800 px-2 py-0.5 text-[11px] text-slate-500 hover:text-slate-300">Dashboard</a>
            </nav>
        </header>

        <form method="post" action="{{ route('admin.printing.settings.update') }}" class="space-y-4 rounded-lg border border-slate-800 bg-slate-900/40 p-4">
            @csrf
            @method('PUT')

            <label class="flex items-center gap-2 text-xs text-slate-300">
                <input type="hidden" name="auto_print_kitchen" value="0">
                <input type="checkbox" name="auto_print_kitchen" value="1" class="rounded border-slate-600" @checked(old('auto_print_kitchen', $settings->auto_print_kitchen))>
                Auto-print kitchen ticket for new lines
            </label>

            <label class="block">
                <span class="mb-0.5 block text-[10px] font-semibold uppercase text-slate-500">Kitchen printer</span>
                <select name="kitchen_printer_id" class="w-full rounded border border-slate-700 bg-slate-950 px-2 py-1.5 text-sm text-white">
                    <option value="">— None —</option>
                    @foreach($printers->filter(fn ($p) => $p->role === \App\Enums\PrinterRole::Kitchen) as $p)
                        <option value="{{ $p->id }}" @selected((int) old('kitchen_printer_id', $settings->kitchen_printer_id) === $p->id)>{{ $p->name }} @if(!$p->is_enabled)(disabled)@endif</option>
                    @endforeach
                </select>
            </label>

            <hr class="border-slate-800">

            <label class="flex items-center gap-2 text-xs text-slate-300">
                <input type="hidden" name="auto_print_cashier" value="0">
                <input type="checkbox" name="auto_print_cashier" value="1" class="rounded border-slate-600" @checked(old('auto_print_cashier', $settings->auto_print_cashier))>
                Auto-print cashier / receipt ticket for new lines
            </label>

            <label class="block">
                <span class="mb-0.5 block text-[10px] font-semibold uppercase text-slate-500">Cashier printer</span>
                <select name="cashier_printer_id" class="w-full rounded border border-slate-700 bg-slate-950 px-2 py-1.5 text-sm text-white">
                    <option value="">— None —</option>
                    @foreach($printers->filter(fn ($p) => $p->role === \App\Enums\PrinterRole::Cashier) as $p)
                        <option value="{{ $p->id }}" @selected((int) old('cashier_printer_id', $settings->cashier_printer_id) === $p->id)>{{ $p->name }} @if(!$p->is_enabled)(disabled)@endif</option>
                    @endforeach
                </select>
            </label>

            <button type="submit" class="w-full rounded bg-emerald-600 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Save settings</button>
        </form>
    </div>
@endsection
