@extends('layouts.guest')

@section('title', 'Table Unavailable')

@section('content')
    <div class="space-y-6 text-center">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.35em] text-amber-400">Table Status</p>
            <h1 class="mt-3 text-2xl font-semibold text-white">Table {{ $table->table_number }} is occupied</h1>
            <p class="mt-3 text-sm leading-6 text-slate-400">{{ $message }}</p>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-950 px-4 py-4 text-sm text-slate-400">
            Please ask staff before scanning again or wait until the current session is finished.
        </div>
    </div>
@endsection
