@extends('layouts.app')

@section('title', 'New order — POS')

@push('head')
    @vite(['resources/js/staff-pos-order.js'])
@endpush

@section('content')
    <script type="application/json" id="staff-pos-catalog">@json($catalogPayload)</script>
    <script type="application/json" id="staff-pos-tables">@json($tablesPayload)</script>

    <div
        id="staff-pos-root"
        class="staff-pos-shell -mx-4 -my-8 flex min-h-[calc(100dvh-5rem)] flex-col gap-3 rounded-2xl bg-slate-900/60 px-3 pb-6 pt-4 ring-1 ring-white/10 sm:px-4"
        data-store-url="{{ route('orders.store') }}"
        data-orders-base="{{ url('/orders') }}"
        data-dashboard-url="{{ route('dashboard') }}"
    >
        {{-- Top bar: table + search --}}
        <header class="flex shrink-0 flex-col gap-3 border-b border-white/10 pb-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0 flex-1">
                <div class="mb-2 flex flex-wrap items-center gap-2">
                    <h1 class="text-xl font-bold tracking-tight text-white sm:text-2xl">Staff POS</h1>
                    <span class="rounded-full bg-emerald-500/20 px-2.5 py-0.5 text-xs font-medium text-emerald-300 ring-1 ring-emerald-500/40">Ordering</span>
                </div>
                <p class="text-sm text-slate-400">Tap a table, pick items, place or send to kitchen.</p>
            </div>
            <div class="flex w-full flex-col gap-2 sm:flex-row sm:items-center lg:w-auto lg:min-w-[320px]">
                <label class="sr-only" for="staff-pos-table-select">Table</label>
                <select
                    id="staff-pos-table-select"
                    class="min-h-[48px] w-full rounded-xl border border-slate-600/80 bg-slate-950 px-4 py-3 text-base font-medium text-white shadow-inner focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/40"
                >
                    <option value="">Select table…</option>
                </select>
                <div class="relative w-full sm:max-w-xs lg:w-72">
                    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-500" aria-hidden="true">⌕</span>
                    <input
                        id="staff-pos-search"
                        type="search"
                        autocomplete="off"
                        placeholder="Search menu…"
                        class="min-h-[48px] w-full rounded-xl border border-slate-600/80 bg-slate-950 py-3 pl-10 pr-3 text-base text-white placeholder:text-slate-500 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/40"
                    />
                </div>
            </div>
        </header>

        {{-- Table chips + session strip --}}
        <section aria-label="Tables" class="shrink-0">
            <div class="mb-2 flex flex-wrap items-center gap-2">
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tables</span>
                <span class="inline-flex items-center gap-2 text-xs text-slate-500">
                    <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-emerald-500"></span> Available</span>
                    <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-red-500"></span> Occupied</span>
                    <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-amber-400"></span> Reserved</span>
                </span>
            </div>
            <div id="staff-pos-table-chips" class="flex max-h-[140px] flex-wrap gap-2 overflow-y-auto pb-1"></div>
            <p id="staff-pos-session-hint" class="mt-2 hidden rounded-lg bg-slate-800/80 px-3 py-2 text-sm text-slate-300 ring-1 ring-white/10"></p>
        </section>

        {{-- Filters: category modes --}}
        <div class="flex shrink-0 flex-wrap gap-2 border-b border-white/5 pb-3">
            <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Browse</span>
            <button type="button" data-pos-filter="all" class="pos-filter-btn rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow">All items</button>
            <button type="button" data-pos-filter="popular" class="pos-filter-btn rounded-full bg-slate-800 px-4 py-2 text-sm font-semibold text-slate-200 ring-1 ring-white/10 hover:bg-slate-700">Popular</button>
            <button type="button" data-pos-filter="recent" class="pos-filter-btn rounded-full bg-slate-800 px-4 py-2 text-sm font-semibold text-slate-200 ring-1 ring-white/10 hover:bg-slate-700">Recent</button>
        </div>

        {{-- 3-column body --}}
        <div class="grid min-h-0 flex-1 grid-cols-1 gap-3 lg:grid-cols-[minmax(160px,200px)_minmax(0,1fr)_minmax(280px,380px)] lg:gap-4">
            {{-- Categories --}}
            <aside aria-label="Categories" class="flex min-h-0 flex-col rounded-xl bg-slate-950/80 ring-1 ring-white/10 lg:max-h-[calc(100dvh-22rem)]">
                <div class="border-b border-white/10 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Categories</div>
                <div id="staff-pos-categories" class="flex flex-row gap-2 overflow-x-auto p-2 lg:flex-col lg:overflow-y-auto lg:overflow-x-hidden lg:p-2"></div>
            </aside>

            {{-- Menu grid --}}
            <section aria-label="Menu items" class="flex min-h-0 flex-col rounded-xl bg-slate-950/50 ring-1 ring-white/10">
                <div class="flex items-center justify-between border-b border-white/10 px-3 py-2">
                    <span id="staff-pos-menu-heading" class="text-sm font-semibold text-slate-200">Menu</span>
                    <span id="staff-pos-item-count" class="text-xs text-slate-500"></span>
                </div>
                <div id="staff-pos-items" class="grid max-h-[min(65vh,560px)] grid-cols-2 gap-3 overflow-y-auto p-3 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4"></div>
            </section>

            {{-- Cart --}}
            <aside aria-label="Current order" class="flex min-h-0 flex-col rounded-xl bg-slate-950/90 ring-1 ring-emerald-500/20">
                <div class="border-b border-emerald-500/20 px-3 py-2">
                    <div class="text-xs font-semibold uppercase tracking-wide text-emerald-400/90">Current order</div>
                    <div id="staff-pos-cart-table-label" class="mt-1 text-sm font-medium text-white">No table selected</div>
                </div>
                <div id="staff-pos-cart-lines" class="max-h-[min(42vh,360px)] flex-1 space-y-2 overflow-y-auto p-3"></div>
                <div class="border-t border-white/10 p-3">
                    <div class="mb-3 flex items-center justify-between text-sm">
                        <span class="text-slate-400">Subtotal</span>
                        <span id="staff-pos-subtotal" class="font-mono text-lg font-bold text-white">¥0</span>
                    </div>
                    <div id="staff-pos-toast" class="mb-3 hidden rounded-lg px-3 py-2 text-sm"></div>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" id="staff-pos-save-draft" class="min-h-[48px] rounded-xl bg-slate-800 px-3 text-sm font-semibold text-white ring-1 ring-white/15 hover:bg-slate-700">Save draft</button>
                        <button type="button" id="staff-pos-load-draft" class="min-h-[48px] rounded-xl bg-slate-800 px-3 text-sm font-semibold text-white ring-1 ring-white/15 hover:bg-slate-700">Load draft</button>
                        <button type="button" id="staff-pos-place" class="col-span-2 min-h-[52px] rounded-xl bg-emerald-600 px-4 text-base font-bold text-white shadow-lg shadow-emerald-900/40 hover:bg-emerald-500 active:scale-[0.99]">Place order</button>
                        <button type="button" id="staff-pos-kitchen" class="min-h-[48px] rounded-xl bg-amber-500/90 px-3 text-sm font-bold text-slate-950 hover:bg-amber-400">Send to kitchen</button>
                        <button type="button" id="staff-pos-checkout" class="min-h-[48px] rounded-xl bg-slate-700 px-3 text-sm font-semibold text-white ring-1 ring-white/15 hover:bg-slate-600">Checkout</button>
                    </div>
                    <p class="mt-2 text-center text-[11px] text-slate-500">Realtime updates use the global dashboard channel after place.</p>
                </div>
            </aside>
        </div>

        {{-- Item customization drawer --}}
        <div id="staff-pos-drawer-overlay" class="fixed inset-0 z-[60] hidden bg-black/60 backdrop-blur-sm" aria-hidden="true"></div>
        <aside
            id="staff-pos-drawer"
            class="fixed right-0 top-0 z-[70] flex h-full w-full max-w-md translate-x-full flex-col bg-slate-950 shadow-2xl ring-1 ring-white/10 transition-transform duration-300 ease-out"
            aria-hidden="true"
        >
            <div class="flex items-center justify-between border-b border-white/10 px-4 py-4">
                <h2 id="staff-pos-drawer-title" class="text-lg font-semibold text-white">Item</h2>
                <button type="button" id="staff-pos-drawer-close" class="rounded-lg p-2 text-slate-400 hover:bg-white/10 hover:text-white" aria-label="Close">✕</button>
            </div>
            <div id="staff-pos-drawer-body" class="flex-1 overflow-y-auto p-4"></div>
            <div class="border-t border-white/10 p-4">
                <button type="button" id="staff-pos-drawer-add" class="min-h-[52px] w-full rounded-xl bg-emerald-600 text-base font-bold text-white hover:bg-emerald-500">Add to order</button>
            </div>
        </aside>
    </div>
@endsection
