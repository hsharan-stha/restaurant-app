@extends('layouts.app')

@section('title', 'Staff POS')

@push('head')
    @vite(['resources/js/staff-pos-order.js'])
@endpush

@section('content')
    <script type="application/json" id="staff-pos-catalog">@json($catalogPayload)</script>
    <script type="application/json" id="staff-pos-tables">@json($tablesPayload)</script>

    <div
        id="staff-pos-root"
        class="staff-pos-shell -mx-4 -my-5 flex min-h-[calc(100dvh-6rem)] flex-col gap-2 rounded-lg border border-slate-800/90 bg-slate-950/50 px-2 pb-3 pt-2 sm:-my-6 sm:px-3"
        data-store-url="{{ route('orders.store') }}"
        data-orders-base="{{ url('/orders') }}"
        data-dashboard-url="{{ route('dashboard') }}"
    >
        <header class="flex shrink-0 flex-wrap items-start justify-between gap-2 border-b border-slate-800/90 pb-2">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-sm font-semibold tracking-tight text-white">Staff POS</h1>
                    <span class="rounded border border-emerald-700/60 bg-emerald-950/50 px-1.5 py-px text-[10px] font-medium text-emerald-300">Order</span>
                </div>
                <p class="text-[11px] text-slate-500">Table → items → cart</p>
            </div>
            <div class="flex w-full max-w-xl flex-[1_1_16rem] flex-col gap-1.5 sm:flex-row sm:items-center">
                <label class="sr-only" for="staff-pos-table-select">Table</label>
                <select
                    id="staff-pos-table-select"
                    class="h-9 w-full shrink-0 rounded-md border border-slate-700 bg-slate-950 px-2 text-xs font-medium text-white focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500/40 sm:max-w-[11rem]"
                >
                    <option value="">Table…</option>
                </select>
                <div class="relative min-w-[8rem] flex-1">
                    <span class="pointer-events-none absolute left-2 top-1/2 -translate-y-1/2 text-[11px] text-slate-500" aria-hidden="true">⌕</span>
                    <input
                        id="staff-pos-search"
                        type="search"
                        autocomplete="off"
                        placeholder="Search…"
                        class="h-9 w-full rounded-md border border-slate-700 bg-slate-950 py-1 pl-7 pr-2 text-xs text-white placeholder:text-slate-600 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500/40"
                    />
                </div>
            </div>
        </header>

        <section aria-label="Tables" class="shrink-0">
            <div class="mb-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[10px] text-slate-500">
                <span class="font-semibold uppercase tracking-wide text-slate-600">Tables</span>
                <span class="inline-flex gap-2">
                    <span class="flex items-center gap-0.5"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>F</span>
                    <span class="flex items-center gap-0.5"><span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>Occ</span>
                    <span class="flex items-center gap-0.5"><span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span>Rsv</span>
                </span>
            </div>
            <div id="staff-pos-table-chips" class="flex max-h-[5.25rem] flex-wrap gap-1 overflow-y-auto"></div>
            <p id="staff-pos-session-hint" class="mt-1 hidden rounded border border-slate-700/70 bg-slate-900 px-2 py-1 text-[11px] text-slate-400"></p>
        </section>

        <div class="flex shrink-0 flex-wrap gap-1 border-b border-slate-800/80 pb-1.5 text-[11px]">
            <span class="mr-1 self-center font-semibold uppercase tracking-wide text-slate-600">View</span>
            <button type="button" data-pos-filter="all" class="pos-filter-btn rounded border border-emerald-600 bg-emerald-900/60 px-2 py-0.5 font-medium text-emerald-100">All</button>
            <button type="button" data-pos-filter="popular" class="pos-filter-btn rounded border border-slate-700 bg-slate-900 px-2 py-0.5 font-medium text-slate-300 hover:bg-slate-800">Popular</button>
            <button type="button" data-pos-filter="recent" class="pos-filter-btn rounded border border-slate-700 bg-slate-900 px-2 py-0.5 font-medium text-slate-300 hover:bg-slate-800">Recent</button>
        </div>

        <div class="grid min-h-0 flex-1 grid-cols-1 gap-2 lg:grid-cols-[9.5rem_minmax(0,1fr)_17.5rem] xl:grid-cols-[10rem_minmax(0,1fr)_19rem] 2xl:grid-cols-[11rem_minmax(0,1fr)_21rem]">
            <aside aria-label="Categories" class="flex min-h-0 flex-col rounded-md border border-slate-800/90 bg-slate-950/80 lg:max-h-[calc(100dvh-12rem)]">
                <div class="border-b border-slate-800 px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-slate-500">Cat</div>
                <div id="staff-pos-categories" class="flex flex-row gap-1 overflow-x-auto p-1 lg:flex-col lg:overflow-y-auto lg:overflow-x-hidden"></div>
            </aside>

            <section aria-label="Menu items" class="flex min-h-0 flex-col rounded-md border border-slate-800/90 bg-slate-950/40">
                <div class="flex items-center justify-between border-b border-slate-800 px-2 py-1">
                    <span id="staff-pos-menu-heading" class="truncate text-xs font-medium text-slate-300">Menu</span>
                    <span id="staff-pos-item-count" class="shrink-0 text-[10px] text-slate-500"></span>
                </div>
                <div id="staff-pos-items" class="grid max-h-[min(62vh,32rem)] auto-rows-fr grid-cols-2 gap-1.5 overflow-y-auto p-1.5 sm:gap-2 md:grid-cols-3 xl:grid-cols-5 2xl:grid-cols-5"></div>
            </section>

            <aside aria-label="Current order" class="flex min-h-0 flex-col rounded-md border border-slate-700/70 bg-slate-950 lg:sticky lg:top-0 lg:max-h-[calc(100dvh-12rem)]">
                <div class="border-b border-slate-800 px-2 py-1">
                    <div class="text-[10px] font-semibold uppercase text-slate-500">Cart</div>
                    <div id="staff-pos-cart-table-label" class="truncate text-[11px] font-medium text-slate-200">No table</div>
                </div>
                <div id="staff-pos-cart-lines" class="min-h-0 flex-1 space-y-1 overflow-y-auto p-1.5"></div>
                <div class="border-t border-slate-800 p-1.5">
                    <div class="mb-1.5 flex items-baseline justify-between text-[11px]">
                        <span class="text-slate-500">Subtotal</span>
                        <span id="staff-pos-subtotal" class="font-mono text-sm font-semibold text-white">¥0</span>
                    </div>
                    <div id="staff-pos-toast" class="mb-1.5 hidden rounded border px-2 py-1 text-[11px]"></div>
                    <div class="grid grid-cols-2 gap-1">
                        <button type="button" id="staff-pos-save-draft" class="h-8 rounded border border-slate-700 bg-slate-900 text-[11px] font-medium text-slate-200 hover:bg-slate-800">Save</button>
                        <button type="button" id="staff-pos-load-draft" class="h-8 rounded border border-slate-700 bg-slate-900 text-[11px] font-medium text-slate-200 hover:bg-slate-800">Load</button>
                        <button type="button" id="staff-pos-place" class="col-span-2 h-9 rounded bg-emerald-600 text-xs font-semibold text-white hover:bg-emerald-500">Place order</button>
                        <button type="button" id="staff-pos-kitchen" class="h-8 rounded bg-amber-500 text-[11px] font-semibold text-slate-950 hover:bg-amber-400">Kitchen</button>
                        <button type="button" id="staff-pos-checkout" class="h-8 rounded border border-slate-600 bg-slate-800 text-[11px] font-medium text-slate-200 hover:bg-slate-700">Checkout</button>
                    </div>
                </div>
            </aside>
        </div>

        <div id="staff-pos-drawer-overlay" class="fixed inset-0 z-[60] hidden bg-black/50" aria-hidden="true"></div>
        <aside
            id="staff-pos-drawer"
            class="fixed right-0 top-0 z-[70] flex h-full w-full max-w-sm translate-x-full flex-col border-l border-slate-800 bg-slate-950 shadow-xl transition-transform duration-200 ease-out"
            aria-hidden="true"
        >
            <div class="flex items-center justify-between border-b border-slate-800 px-3 py-2">
                <h2 id="staff-pos-drawer-title" class="truncate text-sm font-semibold text-white">Item</h2>
                <button type="button" id="staff-pos-drawer-close" class="rounded p-1 text-slate-400 hover:bg-slate-800 hover:text-white" aria-label="Close">✕</button>
            </div>
            <div id="staff-pos-drawer-body" class="flex-1 overflow-y-auto p-3"></div>
            <div class="border-t border-slate-800 p-2">
                <button type="button" id="staff-pos-drawer-add" class="h-9 w-full rounded bg-emerald-600 text-xs font-semibold text-white hover:bg-emerald-500">Add</button>
            </div>
        </aside>
    </div>
@endsection
