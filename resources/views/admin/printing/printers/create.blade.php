@extends('layouts.app')

@section('title', 'Add printer')

@section('content')
    <div class="mx-auto max-w-xl space-y-4 text-[13px]">
        <header class="border-b border-slate-800/90 pb-2">
            <h1 class="text-base font-semibold text-slate-100">Add printer</h1>
            <nav class="mt-2 flex flex-wrap gap-1">
                <a href="{{ route('admin.printing.printers.index') }}" class="text-[11px] text-slate-500 hover:text-slate-300">← Printers</a>
            </nav>
        </header>
        <form method="post" action="{{ route('admin.printing.printers.store') }}" class="space-y-4 rounded-lg border border-slate-800 bg-slate-900/40 p-4">
            @csrf
            @php
                $printer = \App\Models\Printer::make([
                    'name' => '',
                    'connection_type' => \App\Enums\PrinterConnectionType::NetworkEscpos,
                    'role' => \App\Enums\PrinterRole::Kitchen,
                    'host' => '',
                    'port' => 9100,
                    'paper_width' => '80',
                    'is_enabled' => true,
                    'auto_print_enabled' => true,
                ]);
            @endphp
            @include('admin.printing.printers._form')
            <button type="submit" class="w-full rounded bg-emerald-600 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Save printer</button>
        </form>
    </div>
@endsection
