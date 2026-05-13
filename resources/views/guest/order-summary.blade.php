@php
    use App\Enums\OrderStatus;
    $tableLabel = $table->table_name ? $table->table_name : 'Table '.$table->table_number;
@endphp

@extends('layouts.customer')

@section('title', $tableLabel.' · Order')

@push('guest_meta')
    <meta name="guest-table-id" content="{{ $table->id }}">
    <meta name="guest-order-summary-url" content="{{ route('guest.order-summary') }}">
@endpush

@section('content')
    <div class="guest-order-summary mx-auto max-w-7xl px-2 pb-24 pt-3 sm:px-4 sm:pb-10 sm:pt-4">
        <header class="mb-4 flex items-center gap-2 sm:mb-5 sm:gap-3">
            <a
                href="{{ route('guest.menu') }}"
                class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white text-sm font-bold text-orange-700 ring-1 ring-orange-200 shadow-sm sm:h-10 sm:w-10"
                aria-label="Back to menu"
            >←</a>
            <div class="min-w-0">
                <p class="text-[10px] font-bold uppercase tracking-wider text-orange-600">{{ $restaurantDisplayName }}</p>
                <h1 class="truncate text-base font-bold text-slate-950 sm:text-lg">Your order</h1>
                <p class="text-xs text-slate-600">{{ $tableLabel }}</p>
            </div>
        </header>

        @if($activeOrder)
            <section class="mb-4 rounded-xl border border-orange-200/80 bg-white p-3 shadow-sm ring-1 ring-orange-100 sm:p-4" aria-label="Order status">
                <p class="text-[10px] font-bold uppercase tracking-wider text-orange-600">Kitchen</p>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    @foreach([
                        OrderStatus::Pending->value => 'Received',
                        OrderStatus::Preparing->value => 'Cooking',
                        OrderStatus::Completed->value => 'Served',
                    ] as $st => $label)
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $activeOrder->status->value === $st ? 'bg-orange-600 text-white' : 'bg-orange-50 text-slate-600 ring-1 ring-orange-100' }}">
                            {{ $label }}
                        </span>
                    @endforeach
                </div>
                <p class="mt-2 text-xs font-medium text-slate-800">
                    {{ ucfirst(str_replace('_', ' ', $activeOrder->status->value)) }}
                </p>
                @if($activeOrder->checkout_requested_at)
                    <p class="mt-2 rounded-lg bg-amber-50 px-2.5 py-1.5 text-[11px] text-amber-950 ring-1 ring-amber-200">
                        Checkout requested — staff will assist you.
                    </p>
                @endif
            </section>
        @endif

        @include('guest.partials.order-session-panel', [
            'sessionOrders' => $sessionOrders,
            'sessionTotal' => $sessionTotal,
            'activeOrder' => $activeOrder,
            'table' => $table,
            'hasOpenKitchenOrders' => $hasOpenKitchenOrders,
        ])

        <section class="mt-4 rounded-xl border border-orange-100 bg-white p-3 shadow-sm sm:p-4">
            <h2 class="text-sm font-bold text-slate-950">Estimate</h2>
            <p class="mt-0.5 text-[10px] text-slate-500">Tax finalized on bill.</p>
            <dl class="mt-3 space-y-1.5 text-xs text-slate-800 sm:text-sm">
                <div class="flex justify-between">
                    <dt>Subtotal</dt>
                    <dd class="font-semibold tabular-nums">&yen;{{ number_format((float) $sessionTotal, 0) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt>Tax ({{ number_format($taxRate * 100, 1) }}%)</dt>
                    <dd class="font-semibold tabular-nums">&yen;{{ number_format((float) $taxEstimate, 0) }}</dd>
                </div>
                <div class="flex justify-between border-t border-orange-100 pt-2 text-sm font-bold sm:text-base">
                    <dt>Total</dt>
                    <dd class="tabular-nums">&yen;{{ number_format((float) $grandEstimate, 0) }}</dd>
                </div>
            </dl>
        </section>

        <div class="mt-4 space-y-2">
            <a
                href="{{ route('guest.menu') }}"
                class="flex w-full items-center justify-center rounded-xl bg-orange-600 py-3 text-sm font-bold text-white shadow-md"
            >
                Add more
            </a>

            @if($sessionOrders->isNotEmpty())
                @if($hasOpenKitchenOrders)
                    <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] text-amber-950">
                        Some items may still be in the kitchen.
                    </p>
                @endif
                <form method="POST" action="{{ route('guest.checkout') }}" id="guest-checkout-form" class="block">
                    @csrf
                    <button
                        type="submit"
                        class="w-full rounded-xl border-2 border-orange-400 bg-orange-50 py-3 text-sm font-bold text-orange-900 hover:bg-orange-100"
                    >
                        Request checkout
                    </button>
                </form>
            @endif
        </div>
    </div>
@endsection
