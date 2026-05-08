@extends('layouts.dashboard-full')

@section('title', 'Live orders')

@section('content')
    @php
        use App\Enums\OrderStatus;
        use App\Enums\PaymentStatus;
    @endphp

    <div
        id="live-orders-dashboard"
        class="restaurant-dashboard-theme relative min-h-screen"
        data-latest-checkout-request-at="{{ $latestCheckoutRequestAt ?? '' }}"
        data-latest-order-item-id="{{ $latestOrderItemId ?? 0 }}"
    >
        <div class="grid h-screen grid-rows-3 gap-0 lg:grid-cols-3 lg:grid-rows-1">
            @include('partials.order-column', ['title' => 'Pending', 'titleClass' => 'text-amber-400', 'orders' => $pendingOrders, 'showCount' => true])
            @include('partials.order-column', ['title' => 'Preparing', 'titleClass' => 'text-sky-400', 'orders' => $preparingOrders, 'showCount' => true])
            @include('partials.completed-order-column', [
                'title' => 'Completed',
                'titleClass' => 'text-emerald-400',
                'groups' => $completedOrderGroups,
                'completedFilterFrom' => $completedFilterFrom,
                'completedFilterTo' => $completedFilterTo,
            ])
        </div>

        <button
            type="button"
            id="dashboard-action-toggle"
            aria-controls="dashboard-action-panel"
            aria-expanded="false"
            class="fixed bottom-5 right-5 z-40 inline-flex h-14 w-14 items-center justify-center rounded-full bg-orange-500 text-3xl font-light text-white shadow-2xl shadow-orange-950/50 transition hover:scale-105 hover:bg-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-300"
        >
            <span aria-hidden="true">+</span>
        </button>

        <div id="dashboard-action-backdrop" class="pointer-events-none fixed inset-0 z-40 bg-[#120906]/0 opacity-0 transition duration-200"></div>

        <aside
            id="dashboard-action-panel"
            class="restaurant-dashboard-drawer pointer-events-none fixed right-0 top-0 z-50 flex h-screen w-full max-w-sm translate-x-full flex-col opacity-0 shadow-2xl transition duration-300 sm:w-[24rem]"
            aria-hidden="true"
        >
            <div class="flex items-center justify-between border-b border-orange-200/15 px-5 py-4">
                <div>
                    <p class="restaurant-dashboard-kicker text-xs font-semibold uppercase tracking-[0.35em]">Admin Info</p>
                    <h2 class="mt-2 text-2xl font-semibold text-white">{{ auth()->user()->name }}</h2>
                    <p class="restaurant-dashboard-muted mt-1 text-sm">{{ auth()->user()->email }}</p>
                </div>
                <button
                    type="button"
                    id="dashboard-action-close"
                    class="rounded-full border border-orange-200/20 px-3 py-1 text-sm text-orange-100 hover:border-orange-200 hover:text-white"
                >
                    Close
                </button>
            </div>

            <div class="restaurant-dashboard-scroll flex-1 space-y-6 overflow-y-auto px-5 py-5">
                <div class="restaurant-dashboard-inset rounded-3xl border p-4">
                    <p class="restaurant-dashboard-kicker text-xs font-semibold uppercase tracking-[0.25em]">Role</p>
                    <p class="mt-2 text-lg font-semibold text-white">{{ auth()->user()->isAdmin() ? 'Admin' : 'Staff' }}</p>
                    <p class="restaurant-dashboard-muted mt-1 text-sm">Quick actions for managing orders and restaurant data.</p>
                </div>

                <div class="space-y-3">
                    <a href="{{ route('orders.create') }}" target="_blank" rel="noopener noreferrer" class="block rounded-2xl bg-orange-600 px-4 py-4 text-base font-semibold text-white hover:bg-orange-500">
                        New order
                    </a>

                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('categories.index') }}" target="_blank" rel="noopener noreferrer" class="block rounded-2xl border border-orange-200/20 bg-white/5 px-4 py-4 text-base font-semibold text-white hover:border-orange-300 hover:bg-white/10">
                            Category
                        </a>
                        <a href="{{ route('menu-items.index') }}" target="_blank" rel="noopener noreferrer" class="block rounded-2xl border border-orange-200/20 bg-white/5 px-4 py-4 text-base font-semibold text-white hover:border-orange-300 hover:bg-white/10">
                            Item
                        </a>
                        <a href="{{ route('dining-tables.index') }}" target="_blank" rel="noopener noreferrer" class="block rounded-2xl border border-orange-200/20 bg-white/5 px-4 py-4 text-base font-semibold text-white hover:border-orange-300 hover:bg-white/10">
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
