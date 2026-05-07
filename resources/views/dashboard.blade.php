@extends('layouts.app')

@section('title', 'Live orders')

@section('content')
    @php
        use App\Enums\OrderStatus;
        use App\Enums\PaymentStatus;
    @endphp

    <div
        id="live-orders-dashboard"
        data-latest-checkout-request-at="{{ $latestCheckoutRequestAt ?? '' }}"
        data-latest-order-item-id="{{ $latestOrderItemId ?? 0 }}"
    >
        <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-white">Live orders</h1>
                <p class="mt-1 text-sm text-slate-400">Real-time updates announce new orders and checkout requests as soon as they arrive.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <button
                    type="button"
                    id="voice-alert-toggle"
                    class="rounded-lg border border-slate-700 bg-slate-900 px-4 py-2 text-sm font-medium text-slate-200 hover:border-emerald-500 hover:text-white"
                >
                    Enable voice alerts
                </button>
                <a href="{{ route('orders.create') }}" target="_blank" rel="noopener noreferrer" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500">New order</a>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            @include('partials.order-column', ['title' => 'Pending', 'titleClass' => 'text-amber-400', 'orders' => $pendingOrders])
            @include('partials.order-column', ['title' => 'Preparing', 'titleClass' => 'text-sky-400', 'orders' => $preparingOrders])
            @include('partials.order-column', ['title' => 'Completed', 'titleClass' => 'text-emerald-400', 'orders' => $completedOrders])
        </div>
    </div>

@endsection
