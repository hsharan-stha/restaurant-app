@php
    $tableLabel = $table->table_name ? $table->table_name : 'Table '.$table->table_number;
    $sessionStatus = $activeOrder
        ? ucfirst(str_replace('_', ' ', $activeOrder->status->value))
        : 'Ordering';
@endphp

@extends('layouts.customer')

@section('title', $tableLabel.' · Menu')

@push('guest_meta')
    <meta name="guest-table-id" content="{{ $table->id }}">
    <meta name="guest-order-summary-url" content="{{ route('guest.order-summary') }}">
@endpush

@push('scripts')
    @vite(['resources/js/guest-menu.js'])
@endpush

@section('content')
    <div
        id="guest-menu-root"
        class="guest-menu-shell text-sm text-slate-800 antialiased sm:text-[15px] sm:leading-snug"
        data-orders-store-url="{{ route('guest.orders.store') }}"
        data-order-summary-url="{{ route('guest.order-summary') }}"
    >
        {{-- Compact header --}}
        <div
            id="guest-order-success"
            class="pointer-events-none fixed inset-0 z-[80] flex items-center justify-center bg-slate-900/50 opacity-0 transition-opacity duration-300"
            aria-hidden="true"
        >
            <div class="mx-5 max-w-xs rounded-2xl bg-white px-6 py-8 text-center shadow-xl ring-1 ring-emerald-200">
                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-2xl">✓</div>
                <p class="text-base font-bold text-slate-900">Order sent</p>
                <p class="mt-1 text-xs leading-relaxed text-slate-600">You can keep browsing and add more anytime.</p>
            </div>
        </div>

        <header class="relative border-b border-orange-200/60 bg-gradient-to-r from-orange-500 to-amber-500 px-3 py-3 text-white sm:px-4 sm:py-3.5">
            <div class="relative mx-auto flex max-w-7xl items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/20 text-sm font-bold backdrop-blur-sm ring-1 ring-white/25 sm:h-11 sm:w-11 sm:text-base">
                    {{ strtoupper(mb_substr($restaurantDisplayName, 0, 1)) }}
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-[10px] font-semibold uppercase tracking-wider text-white/85 sm:text-xs">{{ $restaurantDisplayName }}</p>
                    <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                        <h1 class="text-base font-bold leading-tight sm:text-lg">{{ $tableLabel }}</h1>
                        <span class="rounded-md bg-black/20 px-1.5 py-0.5 text-[10px] font-medium text-white/95">{{ $sessionStatus }}</span>
                        @if($customerSession->party_size)
                            <span class="rounded-md bg-white/20 px-1.5 py-0.5 text-[10px] font-medium text-white/95">👥 {{ $customerSession->party_size }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </header>

        @include('guest.partials.order-session-panel', [
            'sessionOrders' => $sessionOrders,
            'sessionTotal' => $sessionTotal,
            'activeOrder' => $activeOrder,
            'table' => $table,
            'hasOpenKitchenOrders' => $hasOpenKitchenOrders,
        ])

        {{-- Sticky category tabs --}}
        <div id="guest-category-tabs" class="guest-tabs-sticky z-30 border-b border-orange-100 bg-[#fffaf6]/98 shadow-sm backdrop-blur-md">
            <div class="customer-scroll mx-auto flex max-w-7xl gap-1.5 overflow-x-auto px-2 py-2 sm:gap-2 sm:px-3 sm:py-2">
                @foreach($categories as $idx => $category)
                    <button
                        type="button"
                        data-scroll-category="{{ $category->id }}"
                        class="guest-tab {{ $idx === 0 ? 'guest-tab-active' : '' }}"
                    >
                        {{ $category->name }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Menu grid (bottom padding clears sticky cart bar) --}}
        <div class="guest-menu-scroll-pad mx-auto max-w-7xl space-y-6 px-2 pb-4 pt-3 sm:space-y-8 sm:px-3 sm:pt-4 lg:px-4">
            @foreach($categories as $category)
                <section id="category-{{ $category->id }}" class="scroll-mt-[6.25rem] space-y-2 sm:scroll-mt-28 sm:space-y-3">
                    <div class="flex items-baseline justify-between gap-2 px-0.5">
                        <h2 class="text-sm font-bold text-slate-900 sm:text-base">{{ $category->name }}</h2>
                        <span class="text-[11px] text-slate-400 sm:text-xs">{{ $category->menuItems->count() }}</span>
                    </div>

                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-2 sm:gap-2.5 md:grid-cols-3 md:gap-3 lg:grid-cols-4 lg:gap-3">
                        @foreach($category->menuItems as $item)
                            <article
                                class="guest-menu-card flex flex-col overflow-hidden rounded-xl border border-orange-100/90 bg-white shadow-sm ring-1 ring-orange-50/80"
                                data-menu-item
                                data-id="{{ $item->id }}"
                                data-name="{{ $item->name }}"
                                data-price="{{ number_format((float) $item->price, 2, '.', '') }}"
                            >
                                <div class="relative aspect-[4/3] w-full shrink-0 overflow-hidden bg-gradient-to-br from-orange-50 to-amber-50">
                                    @if($item->image_url)
                                        <img
                                            src="{{ $item->image_url }}"
                                            alt=""
                                            class="h-full w-full object-cover"
                                            loading="lazy"
                                            decoding="async"
                                        >
                                    @else
                                        <span class="flex h-full w-full items-center justify-center text-xl font-bold text-orange-200 sm:text-2xl">{{ strtoupper(mb_substr($item->name, 0, 1)) }}</span>
                                    @endif
                                    <div class="absolute left-1.5 top-1.5 flex flex-wrap gap-1">
                                        @if($item->is_bestseller)
                                            <span class="rounded bg-amber-400/95 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-amber-950 shadow-sm">Top</span>
                                        @endif
                                        @if($item->veg_hint === true)
                                            <span class="flex h-5 w-5 items-center justify-center rounded-full border border-green-600 bg-white/95 text-[9px] font-bold text-green-700" title="Vegetarian">V</span>
                                        @elseif($item->veg_hint === false)
                                            <span class="flex h-5 w-5 items-center justify-center rounded-full border border-red-500 bg-white/95 text-[9px] font-bold text-red-600" title="Non-veg">N</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex min-h-0 flex-1 flex-col p-2 sm:p-2.5">
                                    <h3 class="line-clamp-2 text-xs font-semibold leading-snug text-slate-900 sm:text-sm">{{ $item->name }}</h3>
                                    @if($item->description)
                                        <p class="mt-0.5 line-clamp-1 text-[10px] leading-tight text-slate-500 sm:line-clamp-2 sm:text-[11px]">{{ $item->description }}</p>
                                    @endif
                                    <div class="mt-auto flex flex-wrap items-center justify-between gap-1 pt-2">
                                        <div class="min-w-0">
                                            <p class="text-sm font-bold tabular-nums text-orange-700 sm:text-base">&yen;{{ number_format((float) $item->price, 0) }}</p>
                                            @if($item->prep_minutes)
                                                <p class="text-[10px] text-slate-400">~{{ $item->prep_minutes }}m</p>
                                            @endif
                                        </div>
                                        <div
                                            class="guest-stepper-compact flex shrink-0 items-center gap-1 rounded-2xl bg-orange-50/95 px-1 py-1 ring-1 ring-orange-100/90 shadow-inner shadow-orange-100/40"
                                            aria-label="Quantity"
                                        >
                                            <button type="button" class="guest-stepper-btn guest-stepper-btn-sm" data-quantity-down data-step="down">−</button>
                                            <span
                                                class="guest-qty-display min-w-[1.5rem] text-center text-xs font-bold tabular-nums text-slate-900 sm:min-w-[1.75rem] sm:text-sm"
                                                data-quantity-value
                                            >0</span>
                                            <button type="button" class="guest-stepper-btn guest-stepper-btn-sm" data-quantity-up data-step="up">+</button>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        <div id="guest-mobile-overlay" class="hotpepper-mobile-overlay hidden"></div>

        <aside
            id="guest-cart-drawer"
            class="hotpepper-mobile-drawer hotpepper-mobile-drawer-right guest-cart-drawer-shell flex h-[100dvh] max-h-[100dvh] w-full max-w-sm flex-col overflow-hidden shadow-xl ring-1 ring-orange-100 sm:max-w-sm"
            aria-modal="true"
            aria-labelledby="guest-cart-title"
            role="dialog"
        >
            <div class="flex shrink-0 items-center justify-between border-b border-orange-100 bg-[#fffaf6]/98 px-3 py-2.5 backdrop-blur-md sm:px-4">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-orange-600">Cart</p>
                    <p id="guest-cart-title" class="text-sm font-bold text-slate-950">Review</p>
                </div>
                <button
                    type="button"
                    id="guest-close-cart"
                    class="guest-cart-close-btn rounded-full border border-orange-200 px-3 py-1.5 text-[11px] font-bold uppercase tracking-wide text-orange-800 active:scale-95"
                >
                    Close
                </button>
            </div>

            {{-- Scrollable lines only; summary + CTAs pinned to bottom --}}
            <div class="guest-cart-drawer-body flex min-h-0 flex-1 flex-col">
                <div
                    id="guest-cart-lines-scroll"
                    class="guest-cart-lines-scroll min-h-0 flex-1 overflow-y-auto overscroll-y-contain px-3 pt-2 sm:px-4"
                >
                    <div id="guest-drawer-lines" class="space-y-2 pb-2"></div>
                </div>

                <div
                    id="guest-cart-sticky-footer"
                    class="guest-cart-footer-safe shrink-0 border-t border-orange-100/90 bg-gradient-to-b from-[#fffaf6]/95 to-[#fff5eb]/98 px-3 pt-3 shadow-[0_-12px_32px_-8px_rgba(15,23,42,0.12)] backdrop-blur-md sm:px-4"
                >
                    <div class="flex items-baseline justify-between gap-2 text-sm">
                        <span class="text-slate-600">Subtotal</span>
                        <span class="text-xl font-bold tabular-nums text-slate-950" id="guest-drawer-total">&yen;0</span>
                    </div>
                    <p class="mt-0.5 text-[10px] text-slate-500">Tax on final bill.</p>
                    <button
                        type="button"
                        id="guest-place-order-btn"
                        disabled
                        class="guest-cart-primary-btn mt-3 flex w-full items-center justify-center rounded-2xl bg-gradient-to-r from-orange-600 to-orange-700 py-3.5 text-sm font-bold uppercase tracking-wide text-white shadow-lg shadow-orange-900/15 transition enabled:active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        Place order
                    </button>
                    <a
                        href="{{ route('guest.order-summary') }}"
                        class="guest-cart-secondary-btn mt-2 flex w-full items-center justify-center rounded-2xl border-2 border-orange-200/90 bg-white py-3 text-xs font-semibold text-orange-900 shadow-sm active:scale-[0.98]"
                    >
                        Order summary &amp; checkout →
                    </a>
                </div>
            </div>

            <div id="guest-cart-hidden-inputs" class="hidden" aria-hidden="true"></div>
        </aside>

        {{-- Sticky bottom bar (compact) --}}
        <div
            class="guest-sticky-bar pointer-events-none fixed bottom-0 left-0 right-0 z-40 flex items-stretch gap-2 border-t border-orange-200/80 bg-[#fffaf6]/96 px-2 pb-[calc(0.5rem+env(safe-area-inset-bottom))] pt-2 shadow-[0_-8px_28px_rgba(15,23,42,0.08)] backdrop-blur-md sm:gap-3 sm:px-3"
        >
            <a
                href="{{ route('guest.order-summary') }}"
                class="guest-sticky-status pointer-events-auto flex min-w-0 flex-1 items-center justify-center rounded-xl border border-orange-200 bg-white py-3 text-xs font-semibold text-orange-900 shadow-sm active:scale-[0.98]"
            >
                Status
            </a>
            <button
                type="button"
                id="guest-open-cart"
                class="guest-sticky-cart-btn pointer-events-auto flex min-w-[10rem] items-center justify-between gap-2 rounded-xl bg-gradient-to-r from-orange-600 to-orange-700 px-3.5 py-3 text-left text-white shadow-lg shadow-orange-900/20 sm:min-w-[11.5rem]"
            >
                <span class="text-xs font-semibold tabular-nums" id="guest-cart-bar-total">&yen;0</span>
                <span class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wide">
                    Cart
                    <span
                        class="guest-cart-badge inline-flex min-w-[1.35rem] items-center justify-center rounded-full bg-white/25 px-1.5 py-0.5 text-[10px] font-bold tabular-nums"
                        id="guest-cart-bar-count"
                    >0</span>
                </span>
            </button>
        </div>
    </div>
@endsection
