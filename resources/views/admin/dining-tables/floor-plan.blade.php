@extends('layouts.floor-plan')

@section('title', 'Floor plan')

@section('content')
    <div
        id="floor-plan-root"
        class="flex h-screen min-h-0 flex-col"
        data-url-data="{{ route('dining-tables.floor.data') }}"
        data-url-sync="{{ route('dining-tables.floor.sync') }}"
        data-url-store="{{ route('dining-tables.floor.tables.store') }}"
        data-url-table-template="{{ url('dining-tables/floor/tables/__ID__') }}"
        data-url-dashboard="{{ route('dashboard') }}"
    >
        <header class="flex flex-shrink-0 flex-wrap items-center gap-2 border-b border-slate-800/80 bg-slate-900/95 px-3 py-2 shadow-lg backdrop-blur sm:gap-3 sm:px-4">
            <div class="flex min-w-0 flex-1 items-center gap-2">
                <span class="truncate text-sm font-semibold text-white sm:text-base">Restaurant floor plan</span>
                <span class="hidden rounded-full bg-orange-500/20 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-orange-200 sm:inline">Live layout</span>
            </div>

            <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
                <button type="button" id="fp-add-square" class="rounded-xl border border-slate-600/80 bg-slate-800 px-3 py-2 text-xs font-semibold text-white shadow hover:bg-slate-700 sm:text-sm">+ Square</button>
                <button type="button" id="fp-add-round" class="rounded-xl border border-slate-600/80 bg-slate-800 px-3 py-2 text-xs font-semibold text-white shadow hover:bg-slate-700 sm:text-sm">+ Round</button>
                <button type="button" id="fp-delete" class="rounded-xl border border-rose-700/60 bg-rose-950/80 px-3 py-2 text-xs font-semibold text-rose-100 shadow hover:bg-rose-900 sm:text-sm">Delete</button>
                <button type="button" id="fp-save" class="rounded-xl bg-orange-600 px-4 py-2 text-xs font-semibold text-white shadow hover:bg-orange-500 sm:text-sm">Save layout</button>
                <div class="mx-1 hidden h-6 w-px bg-slate-700 sm:block" aria-hidden="true"></div>
                <button type="button" id="fp-zoom-in" class="rounded-lg border border-slate-600 bg-slate-800 px-2.5 py-2 text-sm font-semibold text-white hover:bg-slate-700" title="Zoom in">＋</button>
                <button type="button" id="fp-zoom-out" class="rounded-lg border border-slate-600 bg-slate-800 px-2.5 py-2 text-sm font-semibold text-white hover:bg-slate-700" title="Zoom out">−</button>
                <button type="button" id="fp-zoom-reset" class="rounded-lg border border-slate-600 bg-slate-800 px-2 py-2 text-xs font-semibold text-slate-200 hover:bg-slate-700" title="Reset zoom">100%</button>
            </div>

            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-700/80 bg-slate-800/50 px-2 py-1.5 sm:px-3">
                <input type="checkbox" id="fp-autosave" class="h-4 w-4 rounded border-slate-600 text-orange-600 focus:ring-orange-500" checked>
                <span class="text-xs text-slate-300 sm:text-sm">Auto-save</span>
            </label>

            <div class="flex flex-wrap items-center gap-1.5">
                <a href="{{ route('dashboard') }}" class="rounded-lg px-2.5 py-1.5 text-xs text-slate-400 hover:text-white sm:text-sm">Dashboard</a>
            </div>

            <p id="fp-status" class="w-full text-[11px] text-slate-500 sm:ml-auto sm:w-auto sm:text-xs"></p>
        </header>

        <div class="relative min-h-0 flex-1 bg-slate-950">
            <div id="konva-container" class="absolute inset-0 bg-slate-950"></div>
            <input type="text" id="fp-inline-editor" class="pointer-events-none fixed z-[70] hidden rounded border border-orange-400 bg-slate-900 px-2 py-1 text-sm text-white shadow-xl" autocomplete="off">

            <div id="fp-drawer-backdrop" class="fixed inset-0 z-[55] bg-slate-950/60 opacity-0 transition-opacity duration-300 pointer-events-none lg:hidden" aria-hidden="true"></div>

            <aside
                id="fp-drawer"
                class="fixed bottom-0 right-0 top-[var(--fp-toolbar-h,56px)] z-[60] flex w-full max-w-[400px] translate-x-full flex-col border-l border-slate-700/80 bg-slate-900 shadow-2xl shadow-black/50 transition-transform duration-300 ease-out sm:top-[var(--fp-toolbar-h,64px)]"
                aria-hidden="true"
            >
                <div class="flex items-start justify-between gap-3 border-b border-slate-800 px-4 py-4">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-[0.25em] text-slate-500">Table details</p>
                        <h2 id="fp-drawer-title" class="mt-1 text-lg font-semibold text-white">—</h2>
                    </div>
                    <button type="button" id="fp-drawer-close" class="rounded-lg border border-slate-600 px-3 py-1.5 text-sm text-slate-200 hover:bg-slate-800" aria-label="Close panel">✕</button>
                </div>

                <div id="fp-drawer-empty" class="flex flex-1 flex-col items-center justify-center gap-2 px-6 py-12 text-center text-sm text-slate-500">
                    <p>Select a table on the floor plan</p>
                    <p class="text-xs text-slate-600">QR code, status, and seating appear here.</p>
                </div>

                <div id="fp-drawer-body" class="hidden flex-1 flex-col overflow-y-auto overscroll-contain">
                    <div class="border-b border-slate-800 px-4 py-4">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Order QR</p>
                        <div class="mt-3 flex justify-center">
                            <div id="fp-drawer-qr" class="inline-flex max-h-52 max-w-full items-center justify-center [&>svg]:h-auto [&>svg]:max-h-48 [&>svg]:w-auto"></div>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button type="button" id="fp-drawer-copy-url" class="flex-1 rounded-xl border border-slate-600 bg-slate-800 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-700 min-[380px]:flex-none">Copy URL</button>
                            <button type="button" id="fp-drawer-download-qr" class="flex-1 rounded-xl border border-slate-600 bg-slate-800 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-700 min-[380px]:flex-none">Download SVG</button>
                            <button type="button" id="fp-drawer-print-qr" class="flex-1 rounded-xl border border-orange-500/40 bg-orange-600/30 px-3 py-2 text-xs font-semibold text-orange-100 hover:bg-orange-600/50 min-[380px]:flex-none">Print</button>
                        </div>
                    </div>

                    <div class="space-y-4 px-4 py-4">
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Status</p>
                            <div class="mt-2 grid grid-cols-3 gap-2">
                                <button type="button" data-status="available" class="fp-status-btn rounded-xl border-2 border-transparent bg-emerald-600/25 px-2 py-2.5 text-center text-xs font-semibold text-emerald-100 ring-1 ring-emerald-500/30 hover:bg-emerald-600/40">Available</button>
                                <button type="button" data-status="reserved" class="fp-status-btn rounded-xl border-2 border-transparent bg-amber-600/25 px-2 py-2.5 text-center text-xs font-semibold text-amber-100 ring-1 ring-amber-500/30 hover:bg-amber-600/40">Reserved</button>
                                <button type="button" data-status="occupied" class="fp-status-btn rounded-xl border-2 border-transparent bg-rose-600/25 px-2 py-2.5 text-center text-xs font-semibold text-rose-100 ring-1 ring-rose-500/30 hover:bg-rose-600/40">Occupied</button>
                            </div>
                        </div>

                        <div class="grid gap-3">
                            <label class="block text-xs font-medium text-slate-400">
                                Table name
                                <input type="text" id="fp-drawer-name" class="mt-1 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white focus:border-orange-500 focus:outline-none focus:ring-1 focus:ring-orange-500" autocomplete="off">
                            </label>
                            <label class="block text-xs font-medium text-slate-400">
                                Seat capacity
                                <input type="number" id="fp-drawer-seats" min="1" max="99" class="mt-1 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white focus:border-orange-500 focus:outline-none">
                            </label>
                            <button type="button" id="fp-drawer-apply" class="w-full rounded-xl bg-orange-600 py-2.5 text-sm font-semibold text-white hover:bg-orange-500">Save name &amp; seats</button>
                        </div>

                        <dl class="grid gap-2 rounded-2xl border border-slate-800 bg-slate-950/50 p-3 text-sm">
                            <div class="flex justify-between gap-2"><dt class="text-slate-500">Shape</dt><dd id="fp-drawer-shape" class="font-medium text-slate-200">—</dd></div>
                            <div class="flex justify-between gap-2"><dt class="text-slate-500">Position</dt><dd id="fp-drawer-position" class="font-mono text-xs text-slate-300">—</dd></div>
                            <div class="flex justify-between gap-2"><dt class="text-slate-500">Table color</dt><dd class="flex items-center gap-2"><span id="fp-drawer-color-swatch" class="h-5 w-5 rounded-md border border-slate-600 shadow-inner"></span><span id="fp-drawer-color-label" class="text-slate-300">—</span></dd></div>
                            <div class="flex justify-between gap-2"><dt class="text-slate-500">Updated</dt><dd id="fp-drawer-updated" class="text-xs text-slate-400">—</dd></div>
                        </dl>
                    </div>
                </div>
            </aside>
        </div>
    </div>
@endsection
