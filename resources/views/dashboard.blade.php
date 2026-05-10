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
        data-url-menu-catalog="{{ route('orders.menu.catalog') }}"
    >
        <header class="relative z-30 flex flex-shrink-0 flex-wrap items-center gap-2 border-b border-orange-950/40 bg-[#120906]/95 px-3 py-2 shadow-lg backdrop-blur sm:gap-3 sm:px-4">
            <div class="flex min-w-0 flex-1 items-center gap-3">
                <span class="truncate text-sm font-semibold text-orange-50 sm:text-base">Restaurant dashboard</span>
                <span id="df-live-count" class="rounded-full bg-orange-600/30 px-2.5 py-0.5 text-xs font-semibold text-orange-100">0 live</span>
                <span
                    id="df-ws-status"
                    class="rounded-full border border-orange-900/60 bg-black/30 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-orange-400"
                    title="Realtime connection"
                >Poll</span>
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

            <div class="flex flex-wrap items-center gap-2 border-t border-orange-950/30 pt-2 sm:border-0 sm:pt-0">
                @if(auth()->user()->isAdmin())
                    <a href="{{ route('dining-tables.index') }}" class="rounded-lg border border-orange-700/50 px-2.5 py-1.5 text-xs text-orange-200 hover:bg-orange-950 sm:text-sm">Edit floor plan</a>
                @endif
                <a href="{{ route('orders.create') }}" class="rounded-lg border border-orange-700/50 px-2.5 py-1.5 text-xs text-orange-200 hover:bg-orange-950 sm:text-sm">New order</a>
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
                class="fixed bottom-0 right-0 top-[var(--df-toolbar-h,120px)] z-50 flex w-full max-w-[420px] translate-x-full flex-col border-l border-orange-900/60 bg-[#120906] shadow-2xl transition-transform duration-300 ease-out"
                aria-hidden="true"
            >
                <div class="flex items-start justify-between gap-3 border-b border-orange-950/50 px-4 py-4">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-[0.25em] text-orange-900">Table</p>
                        <h2 id="df-drawer-title" class="mt-1 text-lg font-semibold text-orange-50">—</h2>
                        <p id="df-drawer-meta" class="mt-1 text-xs text-orange-700"></p>
                    </div>
                    <button type="button" id="df-drawer-close" class="rounded-lg border border-orange-800 px-3 py-1.5 text-sm text-orange-200 hover:bg-orange-950">✕</button>
                </div>

                <div id="df-drawer-empty" class="flex flex-1 flex-col items-center justify-center gap-2 px-6 py-12 text-center text-sm text-orange-900">
                    <p>Select a table on the floor plan</p>
                </div>

                <div id="df-drawer-body" class="hidden flex-1 flex-col overflow-hidden">
                    <div class="flex-1 overflow-y-auto px-4 py-4">
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
        </div>
    </div>
@endsection
