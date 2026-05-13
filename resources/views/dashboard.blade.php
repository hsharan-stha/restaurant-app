@extends('layouts.dashboard-full')

@section('title', 'Live orders')

@section('content')
    <div
        id="dashboard-floor-root"
        class="relative flex h-screen min-h-0 flex-col bg-[#0c0a09]"
        data-url-floor-state="{{ route('dashboard.floor.state') }}"
        data-url-panel-template="{{ url('/dashboard/floor/tables') }}/__ID__/panel"
        data-url-order-status-template="{{ url('/orders') }}/__ORDER__/status"
        data-url-orders-base="{{ url('/orders') }}"
        data-url-item-preparing-template="{{ url('/orders') }}/__ORDER__/items/__ITEM__/mark-preparing"
        data-url-item-ready-template="{{ url('/orders') }}/__ORDER__/items/__ITEM__/mark-ready"
        data-url-item-deliver-template="{{ url('/orders') }}/__ORDER__/items/__ITEM__/deliver"
        data-url-deliver-all-ready-template="{{ url('/orders') }}/__ORDER__/deliver-all-ready"
        data-url-menu-catalog="{{ route('orders.menu.catalog') }}"
    >
        <header class="relative z-30 flex flex-shrink-0 flex-wrap items-center gap-2 border-b border-orange-950/40 bg-[#120906]/95 px-3 py-2 shadow-lg backdrop-blur sm:gap-3 sm:px-4">
            <div class="flex min-w-0 flex-1 items-center gap-3">
                <span class="truncate text-sm font-semibold text-orange-50 sm:text-base">Restaurant dashboard</span>
                <span id="df-live-count" class="rounded-full bg-orange-600/30 px-2.5 py-0.5 text-xs font-semibold text-orange-100">0 live</span>
                <span
                    id="df-ws-status"
                    class="rounded-full border border-orange-900/60 bg-black/30 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-orange-400"
                    title="HTTP polling (no WebSocket)"
                >Poll</span>
                <div class="relative" id="df-actions-menu-wrap">
                    <button
                        type="button"
                        id="df-actions-menu-btn"
                        class="flex h-9 w-9 items-center justify-center rounded-lg border border-orange-800/70 bg-orange-950/60 text-orange-100 transition hover:bg-orange-900/70 focus:outline-none focus:ring-2 focus:ring-orange-500/50 active:scale-95"
                        aria-expanded="false"
                        aria-haspopup="true"
                        aria-controls="df-actions-menu-panel"
                    >
                        <span class="sr-only">Actions menu</span>
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <circle cx="12" cy="5" r="2" />
                            <circle cx="12" cy="12" r="2" />
                            <circle cx="12" cy="19" r="2" />
                        </svg>
                    </button>
                    <div
                        id="df-actions-menu-panel"
                        role="menu"
                        aria-labelledby="df-actions-menu-btn"
                        class="absolute left-0 top-full z-50 mt-1 hidden min-w-[12.5rem] origin-top-left rounded-lg border border-orange-900/70 bg-[#1a120b] py-1 shadow-2xl shadow-black/50 ring-1 ring-orange-950/60"
                    >
                        <a
                            href="{{ route('orders.create') }}"
                            role="menuitem"
                            class="block px-3 py-2 text-xs font-medium text-orange-100 hover:bg-orange-950/80"
                        >New order</a>
                        @if(auth()->user()->isAdmin())
                            <div class="my-1 h-px bg-orange-900/70" role="separator"></div>
                            <p class="px-3 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-orange-700">Admin</p>
                            <a
                                href="{{ route('dining-tables.index') }}"
                                role="menuitem"
                                class="block px-3 py-1.5 text-xs text-orange-200 hover:bg-orange-950/80"
                            >Edit floor plan</a>
                            <a
                                href="{{ route('reporting.completed-orders') }}"
                                role="menuitem"
                                class="block px-3 py-1.5 text-xs text-orange-200 hover:bg-orange-950/80"
                            >Reporting · completed orders</a>
                            <a
                                href="{{ route('reporting.monthly-item-sales-matrix') }}"
                                role="menuitem"
                                class="block px-3 py-1.5 text-xs text-orange-200 hover:bg-orange-950/80"
                            >Reporting · item sales matrix</a>
                            <a
                                href="{{ route('reporting.delivery-performance') }}"
                                role="menuitem"
                                class="block px-3 py-1.5 text-xs text-orange-200 hover:bg-orange-950/80"
                            >Reporting · delivery performance</a>
                            <a
                                href="{{ route('menu-items.index') }}"
                                role="menuitem"
                                class="block px-3 py-1.5 text-xs text-orange-200 hover:bg-orange-950/80"
                            >Menu items</a>
                            <a
                                href="{{ route('categories.index') }}"
                                role="menuitem"
                                class="block px-3 py-1.5 text-xs text-orange-200 hover:bg-orange-950/80"
                            >Categories</a>
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex flex-1 flex-wrap items-center gap-2 sm:max-w-md sm:flex-initial">
                <label class="sr-only" for="df-search">Search tables</label>
                <input
                    id="df-search"
                    type="search"
                    placeholder="Search table…"
                    class="min-w-[8rem] flex-1 rounded-xl border border-orange-900/50 bg-black/30 px-3 py-2 text-sm text-orange-50 placeholder:text-orange-900 focus:border-orange-500 focus:outline-none focus:ring-1 focus:ring-orange-400 sm:w-56"
                    autocomplete="off"
                >
            </div>

            <div class="flex flex-wrap items-center gap-1.5">
                <button type="button" id="df-kitchen" class="rounded-xl border border-orange-800/60 bg-orange-950/50 px-3 py-2 text-xs font-semibold text-orange-100 hover:bg-orange-900/60 sm:text-sm">Kitchen mode</button>
                <button type="button" id="df-fullscreen" class="rounded-xl border border-orange-800/60 bg-orange-950/50 px-3 py-2 text-xs font-semibold text-orange-100 hover:bg-orange-900/60 sm:text-sm">Fullscreen</button>
                <button type="button" id="df-zoom-in" class="rounded-lg border border-orange-800 bg-orange-950 px-2.5 py-2 text-sm text-orange-100 hover:bg-orange-900">＋</button>
                <button type="button" id="df-zoom-out" class="rounded-lg border border-orange-800 bg-orange-950 px-2.5 py-2 text-sm text-orange-100 hover:bg-orange-900">−</button>
                <button type="button" id="df-zoom-reset" class="rounded-lg border border-orange-800 bg-orange-950 px-2 py-2 text-xs text-orange-200 hover:bg-orange-900">100%</button>
            </div>

            <div class="relative z-40 flex flex-wrap items-center gap-1.5 border-t border-orange-950/30 pt-2 sm:border-0 sm:pt-0">
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="rounded-lg px-2 py-1.5 text-xs text-orange-600 hover:text-orange-300 sm:text-sm">Logout</button>
                </form>
            </div>
        </header>

        <div class="relative min-h-0 flex-1">
            <div id="df-konva-container" class="absolute inset-0 touch-manipulation bg-[#0c0a09]"></div>

            <div id="df-drawer-backdrop" class="fixed inset-0 z-40 bg-black/50 opacity-0 transition-opacity duration-300 pointer-events-none lg:hidden"></div>

            <aside
                id="df-drawer"
                class="fixed bottom-0 right-0 top-[var(--df-toolbar-h,120px)] z-50 flex w-full max-w-[min(100%,24rem)] translate-x-full flex-col border-l border-orange-900/60 bg-[#120906] shadow-2xl transition-transform duration-300 ease-out sm:max-w-[26rem] lg:max-w-[28rem]"
                aria-hidden="true"
            >
                <div class="flex items-start justify-between gap-2 border-b border-orange-950/50 px-3 py-3 sm:gap-3 sm:px-4 sm:py-3.5">
                    <div class="min-w-0 flex-1">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.25em] text-orange-900">Table</p>
                        <h2 id="df-drawer-title" class="mt-0.5 truncate text-base font-semibold text-orange-50 sm:text-lg">—</h2>
                        <p id="df-drawer-meta" class="mt-1 text-xs text-orange-700"></p>
                    </div>
                    <button type="button" id="df-drawer-close" class="shrink-0 rounded-lg border border-orange-800 px-2.5 py-1.5 text-sm text-orange-200 hover:bg-orange-950 sm:px-3">✕</button>
                </div>

                <div id="df-drawer-empty" class="flex flex-1 flex-col items-center justify-center gap-2 px-4 py-10 text-center text-sm text-orange-900 sm:px-6 sm:py-12">
                    <p>Select a table on the floor plan</p>
                </div>

                <div id="df-drawer-body" class="hidden flex-1 flex-col overflow-hidden">
                    <div class="flex-1 overflow-y-auto px-3 py-3 sm:px-4 sm:py-4">
                        <section class="mb-6">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-orange-900">Dining session actions</p>
                            <div id="df-session-actions" class="mt-2"></div>
                        </section>

                        <section class="mb-6">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-orange-900">Active orders</p>
                            <div id="df-active-orders" class="mt-2 space-y-3 text-sm text-orange-100"></div>
                        </section>

                        <section class="mb-6">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-orange-900">Kitchen actions</p>
                            <div id="df-status-actions" class="mt-2 flex flex-wrap gap-2"></div>
                        </section>

                        <section>
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-orange-900">Order history (sessions)</p>
                            <div id="df-session-history" class="mt-2 space-y-3"></div>
                        </section>
                    </div>
                </div>
            </aside>

            <div id="df-mobile-session-bar" class="pointer-events-none fixed bottom-0 left-0 right-0 z-30 hidden border-t border-orange-900/50 bg-[#120906]/95 p-2 lg:hidden"></div>
        </div>
    </div>

    <button
        type="button"
        id="df-test-sound"
        class="fixed bottom-4 right-4 z-[65] rounded-full border border-orange-700 bg-orange-600 px-4 py-2 text-xs font-semibold text-white shadow-lg hover:bg-orange-500"
    >
        Test Sound
    </button>

    <div id="df-checkout-modal" class="fixed inset-0 z-[70] hidden items-end justify-center bg-black/70 p-3 sm:items-center">
        <div class="w-full max-w-md rounded-xl border border-orange-800 bg-[#1a120b] p-4 shadow-2xl">
            <p class="text-xs font-semibold uppercase tracking-wider text-orange-700">Confirm checkout</p>
            <h3 class="mt-1 text-lg font-semibold text-orange-50">Checkout this dining session?</h3>
            <p class="mt-2 text-sm text-orange-200">Are you sure you want to checkout this dining session?</p>
            <div id="df-checkout-summary" class="mt-3 rounded-lg border border-orange-900/60 bg-black/20 p-3 text-sm text-orange-100"></div>
            <div class="mt-4 flex flex-wrap justify-end gap-2">
                <button type="button" id="df-checkout-cancel" class="rounded-lg border border-orange-700 px-3 py-2 text-sm text-orange-200 hover:bg-orange-950">Cancel</button>
                <a href="#" id="df-checkout-confirm" class="rounded-lg bg-rose-700 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-600">Confirm Checkout</a>
            </div>
        </div>
    </div>
@endsection
