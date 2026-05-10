@extends('layouts.app')

@section('title', 'Categories · Catalog')

@push('head')
    <style>
        .catalog-shell { font-size: 0.8125rem; line-height: 1.35; }
        .catalog-toast-enter { opacity: 0; transform: translateY(6px); }
        .catalog-toast-active { opacity: 1; transform: translateY(0); transition: opacity 0.2s ease, transform 0.2s ease; }
    </style>
@endpush

@section('content')
    <div
        id="catalog-categories"
        class="catalog-shell mx-auto flex max-h-[calc(100vh-7rem)] min-h-[32rem] max-w-[1600px] flex-col gap-2"
        data-page="categories"
        data-url-list="{{ route('admin.catalog.categories.index') }}"
        data-url-store="{{ route('admin.catalog.categories.store') }}"
        data-url-patch="{{ url('/admin/catalog/categories/__ID__') }}"
        data-url-toggle="{{ url('/admin/catalog/categories/__ID__/toggle-active') }}"
        data-url-delete="{{ url('/admin/catalog/categories/__ID__') }}"
        data-menu-items-url="{{ route('menu-items.index') }}"
        data-dashboard-url="{{ route('dashboard') }}"
    >
        <header class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-b border-slate-800/90 pb-2">
            <div class="flex min-w-0 flex-wrap items-center gap-x-4 gap-y-1">
                <div>
                    <h1 class="text-sm font-semibold tracking-tight text-slate-100 sm:text-base">Categories</h1>
                    <p class="text-[10px] text-slate-500">Restaurant catalog · Sort &amp; visibility</p>
                </div>
                <nav class="flex flex-wrap gap-1">
                    <a href="{{ route('menu-items.index') }}" class="rounded border border-slate-700 bg-slate-900 px-2 py-0.5 text-[11px] text-slate-300 hover:bg-slate-800">Menu items</a>
                    <a href="{{ route('dashboard') }}" class="rounded border border-slate-800 px-2 py-0.5 text-[11px] text-slate-500 hover:text-slate-300">Dashboard</a>
                </nav>
            </div>
            <button type="button" data-cc-new class="touch-manipulation rounded bg-emerald-600 px-3 py-1 text-xs font-semibold text-white hover:bg-emerald-500 active:scale-[0.98]">＋ Quick add</button>
        </header>

        <div class="grid min-h-0 flex-1 grid-cols-1 gap-3 lg:grid-cols-[1fr,minmax(340px,38%)] xl:gap-4">
            <section class="flex min-h-0 flex-col rounded-lg border border-slate-800 bg-slate-900/35">
                <div class="flex shrink-0 flex-wrap items-center gap-2 border-b border-slate-800/90 p-2">
                    <label class="relative flex-1 min-w-[11rem]">
                        <span class="sr-only">Search</span>
                        <input type="search" data-cc-q placeholder="Search categories…" autocomplete="off" class="catalog-input w-full rounded border border-slate-700 bg-slate-950 py-1.5 pl-2 pr-2 text-xs text-white placeholder:text-slate-600">
                    </label>
                </div>
                <div class="min-h-0 flex-1 overflow-auto">
                    <table class="w-full border-collapse text-left text-xs tabular-nums">
                        <thead class="sticky top-0 z-10 border-b border-slate-700 bg-slate-950/95 text-[10px] font-semibold uppercase tracking-wide text-slate-500 backdrop-blur">
                            <tr>
                                <th class="px-2 py-1.5">Name</th>
                                <th class="w-16 px-1 py-1.5 text-right">Items</th>
                                <th class="w-20 px-1 py-1.5">Status</th>
                                <th class="w-14 px-1 py-1.5 text-right">Sort</th>
                                <th class="w-28 px-1 py-1.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody data-cc-tbody class="divide-y divide-slate-800/90 text-slate-200"></tbody>
                    </table>
                    <p data-cc-empty class="hidden px-3 py-6 text-center text-xs text-slate-500">No categories match.</p>
                </div>
            </section>

            <aside class="flex min-h-0 flex-col rounded-lg border border-slate-800 bg-slate-900/50">
                <div class="border-b border-slate-800/90 px-2.5 py-2">
                    <h2 class="text-xs font-semibold text-slate-100" data-cc-panel-title>Edit category</h2>
                    <p class="text-[10px] text-slate-500" data-cc-panel-sub>Save updates instantly</p>
                </div>
                <form data-cc-form class="flex min-h-0 flex-1 flex-col overflow-hidden">
                    <input type="hidden" name="id" data-cc-id value="">
                    <div class="space-y-2 overflow-y-auto p-2.5 pb-24">
                        <label class="block">
                            <span class="mb-0.5 block text-[10px] font-medium uppercase tracking-wide text-slate-500">Name</span>
                            <input name="name" required maxlength="255" class="catalog-input w-full rounded border border-slate-700 bg-slate-950 px-2 py-1.5 text-xs text-white" placeholder="e.g. Noodles">
                        </label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="block">
                                <span class="mb-0.5 block text-[10px] font-medium uppercase tracking-wide text-slate-500">Sort</span>
                                <input name="sort_order" type="number" min="0" max="65535" value="0" class="catalog-input w-full rounded border border-slate-700 bg-slate-950 px-2 py-1.5 text-xs text-white">
                            </label>
                            <label class="flex cursor-pointer items-end gap-2 pb-1">
                                <input name="is_active" type="checkbox" value="1" checked class="h-4 w-4 rounded border-slate-600 bg-slate-950 text-emerald-600">
                                <span class="text-xs text-slate-300">Active</span>
                            </label>
                        </div>
                        <label class="block">
                            <span class="mb-0.5 block text-[10px] font-medium uppercase tracking-wide text-slate-500">Icon (emoji or short code)</span>
                            <input name="icon" maxlength="64" class="catalog-input w-full rounded border border-slate-700 bg-slate-950 px-2 py-1.5 text-xs text-white" placeholder="🍜">
                        </label>
                    </div>
                    <div class="sticky bottom-0 flex flex-wrap gap-1.5 border-t border-slate-800 bg-slate-950/95 p-2 backdrop-blur">
                        <button type="submit" class="touch-manipulation rounded bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500">Save</button>
                        <button type="button" data-cc-clear class="touch-manipulation rounded border border-slate-600 px-2.5 py-1.5 text-xs text-slate-300 hover:bg-slate-800">New</button>
                    </div>
                </form>
            </aside>
        </div>

        <div data-catalog-toast-host class="pointer-events-none fixed right-3 top-20 z-[100] flex max-w-sm flex-col gap-1 sm:right-5"></div>
    </div>
@endsection

@push('scripts')
    @vite(['resources/js/catalog-admin.js'])
@endpush
