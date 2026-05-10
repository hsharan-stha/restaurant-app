@extends('layouts.customer')

@section('title', 'Scan table QR')

@section('content')
    <div class="flex min-h-[70vh] flex-col items-center justify-center px-6 text-center">
        <div class="max-w-sm rounded-3xl border border-orange-200 bg-white px-8 py-10 shadow-lg ring-1 ring-orange-100">
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-orange-600">{{ $restaurantDisplayName }}</p>
            <h1 class="mt-3 text-xl font-bold text-slate-900">Scan your table QR code</h1>
            <p class="mt-3 text-sm leading-relaxed text-slate-600">
                Point your camera at the QR sticker on your table to open the menu. No account or login needed.
            </p>
        </div>
    </div>
@endsection
