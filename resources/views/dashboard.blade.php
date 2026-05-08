@extends('layouts.dashboard-full')

@section('title', 'Live orders')

@section('content')
    @php
        use App\Enums\OrderStatus;
        use App\Enums\PaymentStatus;
    @endphp

    <div
        id="live-orders-dashboard"
        class="relative min-h-screen bg-slate-950"
        data-latest-checkout-request-at="{{ $latestCheckoutRequestAt ?? '' }}"
        data-latest-order-item-id="{{ $latestOrderItemId ?? 0 }}"
    >
        <div class="grid min-h-screen gap-0 lg:grid-cols-3">
            @include('partials.order-column', ['title' => 'Pending', 'titleClass' => 'text-amber-400', 'orders' => $pendingOrders])
            @include('partials.order-column', ['title' => 'Preparing', 'titleClass' => 'text-sky-400', 'orders' => $preparingOrders])
            @include('partials.order-column', ['title' => 'Completed', 'titleClass' => 'text-emerald-400', 'orders' => $completedOrders])
        </div>

        <button
            type="button"
            id="dashboard-action-toggle"
            aria-controls="dashboard-action-panel"
            aria-expanded="false"
            class="fixed bottom-5 right-5 z-40 inline-flex h-14 w-14 items-center justify-center rounded-full bg-emerald-500 text-3xl font-light text-white shadow-2xl shadow-emerald-950/50 transition hover:scale-105 hover:bg-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-300"
        >
            <span aria-hidden="true">+</span>
        </button>

        <div id="dashboard-action-backdrop" class="pointer-events-none fixed inset-0 z-40 bg-slate-950/0 opacity-0 transition duration-200"></div>

        <aside
            id="dashboard-action-panel"
            class="pointer-events-none fixed right-0 top-0 z-50 flex h-screen w-full max-w-sm translate-x-full flex-col border-l border-slate-800 bg-slate-950/95 opacity-0 shadow-2xl transition duration-300 sm:w-[24rem]"
            aria-hidden="true"
        >
            <div class="flex items-center justify-between border-b border-slate-800 px-5 py-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.35em] text-emerald-400">Admin Info</p>
                    <h2 class="mt-2 text-2xl font-semibold text-white">{{ auth()->user()->name }}</h2>
                    <p class="mt-1 text-sm text-slate-400">{{ auth()->user()->email }}</p>
                </div>
                <button
                    type="button"
                    id="dashboard-action-close"
                    class="rounded-full border border-slate-700 px-3 py-1 text-sm text-slate-300 hover:border-white hover:text-white"
                >
                    Close
                </button>
            </div>

            <div class="flex-1 space-y-6 overflow-y-auto px-5 py-5">
                <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-500">Role</p>
                    <p class="mt-2 text-lg font-semibold text-white">{{ auth()->user()->isAdmin() ? 'Admin' : 'Staff' }}</p>
                    <p class="mt-1 text-sm text-slate-400">Quick actions for managing orders and restaurant data.</p>
                </div>

                <div class="space-y-3">
                    <a href="{{ route('orders.create') }}" target="_blank" rel="noopener noreferrer" class="block rounded-2xl bg-emerald-600 px-4 py-4 text-base font-semibold text-white hover:bg-emerald-500">
                        New order
                    </a>

                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('categories.index') }}" target="_blank" rel="noopener noreferrer" class="block rounded-2xl border border-slate-700 bg-slate-900 px-4 py-4 text-base font-semibold text-white hover:border-sky-500 hover:bg-slate-800">
                            Category
                        </a>
                        <a href="{{ route('menu-items.index') }}" target="_blank" rel="noopener noreferrer" class="block rounded-2xl border border-slate-700 bg-slate-900 px-4 py-4 text-base font-semibold text-white hover:border-sky-500 hover:bg-slate-800">
                            Item
                        </a>
                        <a href="{{ route('dining-tables.index') }}" target="_blank" rel="noopener noreferrer" class="block rounded-2xl border border-slate-700 bg-slate-900 px-4 py-4 text-base font-semibold text-white hover:border-sky-500 hover:bg-slate-800">
                            Table
                        </a>
                    @endif

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="block w-full rounded-2xl bg-rose-600 px-4 py-4 text-left text-base font-semibold text-white hover:bg-rose-500">
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </aside>
    </div>

@endsection
