@extends('layouts.app')

@section('title', 'Menu · Catalog')

@push('head')
    <style>
        .catalog-shell { font-size: 0.8125rem; line-height: 1.35; }
        .thumb-sq { width: 36px; height: 36px; }
        .catalog-row-active { outline: 1px solid rgba(251, 191, 36, 0.65); outline-offset: -1px; background: rgba(30, 27, 20, 0.35); }
    </style>
@endpush

@section('content')
    <div
        id="catalog-menu-items"
        class="catalog-shell mx-auto flex max-h-[calc(100vh-7rem)] min-h-[32rem] max-w-[1800px] flex-col gap-2"
        data-page="menu-items"
        data-url-items="{{ route('admin.catalog.menu-items.index') }}"
        data-url-categories="{{ route('admin.catalog.categories.index') }}"
        data-url-store="{{ route('admin.catalog.menu-items.store') }}"
        data-url-patch="{{ url('/admin/catalog/menu-items/__ID__') }}"
        data-url-inline="{{ url('/admin/catalog/menu-items/__ID__/inline') }}"
        data-url-dup="{{ url('/admin/catalog/menu-items/__ID__/duplicate') }}"
        data-url-delete="{{ url('/admin/catalog/menu-items/__ID__') }}"
        data-url-bulk="{{ route('admin.catalog.menu-items.bulk') }}"
        data-categories-url="{{ route('categories.index') }}"
        data-dashboard-url="{{ route('dashboard') }}"
    >
        <header class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-b border-slate-800/90 pb-2">
            <div class="flex min-w-0 flex-wrap items-center gap-x-4 gap-y-1">
                <div>
                    <h1 class="text-sm font-semibold tracking-tight text-slate-100 sm:text-base">Menu items</h1>
                    <p class="text-[10px] text-slate-500">Inventory · Bulk &amp; inline edits</p>
                </div>
                <nav class="flex flex-wrap gap-1">
                    <a href="{{ route('categories.index') }}" class="rounded border border-slate-700 bg-slate-900 px-2 py-0.5 text-[11px] text-slate-300 hover:bg-slate-800">Categories</a>
                    <a href="{{ route('dashboard') }}" class="rounded border border-slate-800 px-2 py-0.5 text-[11px] text-slate-500 hover:text-slate-300">Dashboard</a>
                </nav>
            </div>
            <button type="button" data-mi-new class="touch-manipulation rounded bg-emerald-600 px-3 py-1 text-xs font-semibold text-white hover:bg-emerald-500">＋ New item</button>
        </header>

        <div class="flex shrink-0 flex-wrap items-end gap-2 rounded-lg border border-slate-800 bg-slate-900/40 p-2">
            <label class="min-w-[10rem] flex-1 sm:max-w-xs">
                <span class="mb-0.5 block text-[10px] font-semibold uppercase text-slate-500">Search</span>
                <input type="search" data-mi-q placeholder="Name or description…" autocomplete="off" class="catalog-input w-full rounded border border-slate-700 bg-slate-950 px-2 py-1.5 text-xs text-white">
            </label>
            <label class="min-w-[7rem]">
                <span class="mb-0.5 block text-[10px] font-semibold uppercase text-slate-500">Category</span>
                <select data-mi-filter-cat class="catalog-input w-full rounded border border-slate-700 bg-slate-950 px-1.5 py-1.5 text-xs text-white"></select>
            </label>
            <label class="min-w-[6.5rem]">
                <span class="mb-0.5 block text-[10px] font-semibold uppercase text-slate-500">Available</span>
                <select data-mi-filter-avail class="catalog-input w-full rounded border border-slate-700 bg-slate-950 px-1.5 py-1.5 text-xs text-white">
                    <option value="">All</option>
                    <option value="1">Yes</option>
                    <option value="0">No</option>
                </select>
            </label>
            <label class="min-w-[7rem]">
                <span class="mb-0.5 block text-[10px] font-semibold uppercase text-slate-500">Diet</span>
                <select data-mi-filter-diet class="catalog-input w-full rounded border border-slate-700 bg-slate-950 px-1.5 py-1.5 text-xs text-white">
                    <option value="">All</option>
                    <option value="veg">Veg</option>
                    <option value="non_veg">Non-veg</option>
                </select>
            </label>
            <button type="button" data-mi-refresh class="touch-manipulation rounded border border-slate-600 px-2.5 py-1.5 text-xs text-slate-300 hover:bg-slate-800">Refresh</button>
        </div>

        <div class="grid min-h-0 flex-1 grid-cols-1 gap-3 lg:grid-cols-[1fr,minmax(360px,40%)] xl:gap-4">
            <section class="relative flex min-h-0 flex-col overflow-hidden rounded-lg border border-slate-800 bg-slate-900/35">
                <div class="absolute bottom-14 left-0 right-0 z-20 px-2" data-mi-bulk-wrap style="display: none;">
                    <div class="flex flex-wrap items-center gap-2 rounded-lg border border-amber-500/40 bg-amber-950/90 px-2 py-1.5 text-xs shadow-lg backdrop-blur">
                        <span class="font-semibold text-amber-100" data-mi-bulk-count>0 selected</span>
                        <select data-mi-bulk-move-cat class="max-w-[10rem] rounded border border-slate-600 bg-slate-950 px-1 py-1 text-[11px] text-white"></select>
                        <button type="button" data-mi-bulk-move class="rounded bg-slate-200 px-2 py-1 text-[11px] font-semibold text-slate-900">Move</button>
                        <button type="button" data-mi-bulk-on class="rounded border border-emerald-600 px-2 py-1 text-[11px] text-emerald-200">Activate</button>
                        <button type="button" data-mi-bulk-off class="rounded border border-slate-500 px-2 py-1 text-[11px] text-slate-200">Deactivate</button>
                        <button type="button" data-mi-bulk-del class="rounded border border-red-600/70 px-2 py-1 text-[11px] text-red-300">Delete</button>
                    </div>
                </div>
                <div class="min-h-0 flex-1 overflow-auto pb-14">
                    <table class="w-full border-collapse text-left text-[11px] tabular-nums">
                        <thead class="sticky top-0 z-10 border-b border-slate-700 bg-slate-950/95 text-[10px] font-semibold uppercase tracking-wide text-slate-500 backdrop-blur">
                            <tr>
                                <th class="w-10 px-1 py-1.5">
                                    <input type="checkbox" data-mi-check-all class="h-4 w-4 rounded border-slate-600 bg-slate-950" title="Select all filtered">
                                </th>
                                <th class="w-12 px-0 py-1.5"></th>
                                <th class="min-w-[7rem] px-1 py-1.5">Name</th>
                                <th class="min-w-[7rem] px-1 py-1.5">Category</th>
                                <th class="w-24 px-1 py-1.5 text-right">Price ¥</th>
                                <th class="w-16 px-1 py-1.5 text-center">On</th>
                                <th class="w-[5.25rem] px-1 py-1.5">Diet</th>
                                <th class="w-24 px-1 py-1.5 text-right">More</th>
                            </tr>
                        </thead>
                        <tbody data-mi-tbody class="divide-y divide-slate-800/90 text-slate-200"></tbody>
                    </table>
                    <p data-mi-empty class="hidden px-3 py-6 text-center text-xs text-slate-500">No items match filters.</p>
                </div>
            </section>

            <aside class="flex min-h-0 flex-col rounded-lg border border-slate-800 bg-slate-900/50">
                <div class="border-b border-slate-800/90 px-2.5 py-2">
                    <h2 class="text-xs font-semibold text-slate-100" data-mi-panel-title>Item editor</h2>
                    <p class="text-[10px] text-slate-500" data-mi-panel-sub>New or selected row · Tab-friendly</p>
                </div>
                <div class="relative min-h-0 flex-1 overflow-y-auto pb-36">
                    <form data-mi-form class="space-y-2 p-2.5">
                        <input type="hidden" name="id" data-mi-id value="">
                        <div
                            data-mi-drop
                            class="relative flex min-h-[4.25rem] cursor-pointer flex-col items-center justify-center overflow-hidden rounded border border-dashed border-slate-600 bg-slate-950/60 px-2 py-3 text-[10px] text-slate-500 hover:border-amber-500/50 hover:text-slate-300"
                        >
                            <input type="file" name="image" accept="image/*" data-mi-file class="absolute inset-0 z-[1] cursor-pointer opacity-0">
                            <span class="pointer-events-none text-center leading-tight">Drag image · or tap to browse</span>
                            <div data-mi-preview class="relative mt-2 hidden">
                                <img alt="" class="thumb-sq mx-auto rounded border border-slate-700 object-cover">
                                <button type="button" data-mi-clear-img class="absolute -right-1 -top-1 rounded bg-red-800 px-1 text-[10px] text-white">×</button>
                            </div>
                        </div>
                        <label class="mt-2 block cursor-pointer hidden" data-mi-remove-wrap>
                            <span class="inline-flex items-center gap-1 text-[10px] text-slate-400">
                                <input type="checkbox" name="remove_image" value="1" data-mi-remove class="rounded border-slate-600 bg-slate-950"> Remove current photo
                            </span>
                        </label>
                        <label class="block">
                            <span class="mb-0.5 block text-[10px] font-medium uppercase tracking-wide text-slate-500">Name</span>
                            <input name="name" required maxlength="255" data-mi-tab class="catalog-input w-full rounded border border-slate-700 bg-slate-950 px-2 py-1.5 text-xs text-white">
                        </label>
                        <label class="block">
                            <span class="mb-0.5 block text-[10px] font-medium uppercase tracking-wide text-slate-500">Category</span>
                            <select name="category_id" required data-mi-tab data-mi-form-cat class="catalog-input w-full rounded border border-slate-700 bg-slate-950 px-1.5 py-1.5 text-xs text-white"></select>
                        </label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="block">
                                <span class="mb-0.5 block text-[10px] font-medium uppercase tracking-wide text-slate-500">Price ¥</span>
                                <input name="price" required type="number" min="0" step="0.01" data-mi-tab class="catalog-input w-full rounded border border-slate-700 bg-slate-950 px-2 py-1.5 text-xs text-white">
                            </label>
                            <label class="block">
                                <span class="mb-0.5 block text-[10px] font-medium uppercase tracking-wide text-slate-500">Discount ¥</span>
                                <input name="discount_price" type="number" min="0" step="0.01" data-mi-tab class="catalog-input w-full rounded border border-slate-700 bg-slate-950 px-2 py-1.5 text-xs text-white" placeholder="Optional">
                            </label>
                        </div>
                        <label class="block">
                            <span class="mb-0.5 block text-[10px] font-medium uppercase tracking-wide text-slate-500">Prep (min)</span>
                            <input name="prep_minutes" type="number" min="0" max="32767" data-mi-tab class="catalog-input w-full rounded border border-slate-700 bg-slate-950 px-2 py-1.5 text-xs text-white">
                        </label>
                        <label class="block">
                            <span class="mb-0.5 block text-[10px] font-medium uppercase tracking-wide text-slate-500">Description</span>
                            <textarea name="description" rows="2" maxlength="5000" data-mi-tab class="catalog-input w-full resize-y rounded border border-slate-700 bg-slate-950 px-2 py-1.5 text-xs text-white placeholder:text-slate-600"></textarea>
                        </label>
                        <div class="flex flex-wrap gap-3 pt-1">
                            <label class="flex cursor-pointer items-center gap-1.5 text-xs text-slate-300">
                                <input type="checkbox" name="is_available" value="1" checked class="rounded border-slate-600 bg-slate-950 text-emerald-600">
                                Available
                            </label>
                            <label class="flex cursor-pointer items-center gap-1.5 text-xs text-slate-300">
                                <input type="checkbox" name="is_bestseller" value="1" class="rounded border-slate-600 bg-slate-950 text-amber-500">
                                Bestseller
                            </label>
                            <label class="flex cursor-pointer items-center gap-1.5 text-xs text-slate-300">
                                <input type="checkbox" name="is_popular" value="1" class="rounded border-slate-600 bg-slate-950 text-sky-500">
                                Popular
                            </label>
                        </div>
                        <label class="block">
                            <span class="mb-0.5 block text-[10px] font-medium uppercase tracking-wide text-slate-500">Diet</span>
                            <select name="dietary_type" data-mi-tab class="catalog-input w-full rounded border border-slate-700 bg-slate-950 px-1.5 py-1.5 text-xs text-white">
                                <option value="">—</option>
                                <option value="veg">Vegetarian</option>
                                <option value="non_veg">Non-vegetarian</option>
                            </select>
                        </label>
                        <p data-mi-form-error class="hidden rounded border border-red-800/50 bg-red-950/45 px-2 py-1 text-[11px] text-red-200"></p>
                    </form>
                </div>
                <div class="sticky bottom-0 flex flex-col gap-1 border-t border-slate-800 bg-slate-950/95 p-2 backdrop-blur">
                    <div class="flex flex-wrap gap-1.5">
                        <button type="button" data-mi-save class="touch-manipulation rounded bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500">Save</button>
                        <button type="button" data-mi-save-another class="touch-manipulation rounded border border-emerald-600/70 px-2.5 py-1.5 text-xs text-emerald-200 hover:bg-emerald-950">Save &amp; add another</button>
                        <button type="button" data-mi-save-continue class="touch-manipulation hidden rounded border border-slate-600 px-2.5 py-1.5 text-xs text-slate-200 hover:bg-slate-800 sm:inline-flex">Save &amp; continue</button>
                        <button type="button" data-mi-dup-panel class="touch-manipulation rounded border border-slate-600 px-2 py-1.5 text-xs text-slate-300 hover:bg-slate-800 disabled:opacity-40" disabled>Duplicate</button>
                        <button type="button" data-mi-del-panel class="touch-manipulation rounded border border-red-800/70 px-2 py-1.5 text-xs text-red-300 hover:bg-red-950/50 disabled:opacity-40" disabled>Delete</button>
                    </div>
                    <p class="text-[10px] text-slate-600"><kbd class="rounded border border-slate-700 px-1">⌘</kbd><kbd class="ml-1 rounded border border-slate-700 px-1">↵</kbd> save · inline row edits autopatch</p>
                </div>
            </aside>
        </div>

        <div data-catalog-toast-host class="pointer-events-none fixed right-3 top-20 z-[100] flex max-w-sm flex-col gap-1 sm:right-5"></div>
    </div>
@endsection

@push('scripts')
    @vite(['resources/js/catalog-admin.js'])
@endpush
