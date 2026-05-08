@extends('layouts.customer')

@section('title', 'Table '.$table->table_number.' Menu')

@php
    use App\Enums\OrderStatus;
@endphp

@section('content')
    <div class="hotpepper-shell guest-menu-pro pb-28 lg:pb-10">
        <section class="hotpepper-hero">
            <div class="mx-auto max-w-6xl px-4 pb-6 pt-5 sm:px-6 lg:px-8">
                <div class="space-y-4">
                    <div class="space-y-4">
                        <div>
                            <p class="text-base font-semibold uppercase tracking-[0.22em] text-orange-700">Welcome to your table</p>
                            <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl lg:text-4xl">Table {{ $table->table_number }}</h1>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div id="guest-mobile-overlay" class="hotpepper-mobile-overlay hidden lg:hidden"></div>

        <aside id="guest-category-drawer" class="hotpepper-mobile-drawer hotpepper-mobile-drawer-left lg:hidden">
            <div class="flex items-center justify-between border-b border-orange-100 px-5 py-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.22em] text-orange-500">Browse</p>
                    <h2 class="mt-1 text-xl font-black text-slate-950">Food categories</h2>
                </div>
                <button type="button" data-close-drawer class="hotpepper-drawer-close">Close</button>
            </div>
            <div class="customer-scroll max-h-[calc(100vh-6rem)] overflow-y-auto px-4 py-4">
                <div class="space-y-3">
                @foreach($categories as $category)
                    <a href="#category-{{ $category->id }}" class="hotpepper-drawer-link" data-drawer-link>
                        <span>{{ $category->name }}</span>
                        <span>{{ $category->menuItems->count() }}</span>
                    </a>
                @endforeach
                </div>
            </div>
        </aside>

        @if($sessionOrders->isNotEmpty())
            <section class="mx-auto max-w-6xl px-4 pt-5 sm:px-6 lg:px-8">
                <div class="hotpepper-cart-card">
                    <div class="hotpepper-ticket-header">
                        <div>
                            <p class="hotpepper-ticket-kicker">Session orders</p>
                            <div class="mt-2 flex flex-wrap items-end gap-x-3 gap-y-2">
                                <h2 class="hotpepper-ticket-title">{{ $sessionOrders->count() }} orders placed</h2>
                                <span class="hotpepper-ticket-meta">Table {{ $table->table_number }}</span>
                            </div>
                        </div>
                        <span class="hotpepper-ticket-status">{{ $activeOrder ? $activeOrder->status->value : 'completed' }}</span>
                    </div>
                    <div class="hotpepper-session-slider customer-scroll mt-4 flex gap-3 overflow-x-auto pb-2">
                        <div class="hotpepper-session-card min-w-[220px] rounded-2xl bg-orange-50 px-4 py-4">
                            <p class="text-sm font-bold uppercase tracking-[0.18em] text-orange-600">Session total</p>
                            <p class="mt-2 text-3xl font-black text-slate-950">&yen;{{ number_format((float) $sessionTotal, 0) }}</p>
                        </div>
                        @foreach($sessionOrders as $sessionOrder)
                            <div class="hotpepper-session-card min-w-[320px] rounded-2xl bg-[#fff7f2] px-4 py-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-lg font-semibold text-slate-900 sm:text-xl">Order #{{ $sessionOrder->id }}</p>
                                        <p class="mt-1 text-sm text-slate-500 sm:text-base">{{ ucfirst($sessionOrder->status->value) }}</p>
                                    </div>
                                    <p class="shrink-0 text-lg font-bold text-slate-950 sm:text-xl">&yen;{{ number_format((float) $sessionOrder->total_amount, 0) }}</p>
                                </div>
                                <div class="mt-4 space-y-2">
                                    @foreach($sessionOrder->items as $item)
                                        <div class="rounded-xl bg-white/80 px-3 py-3">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <p class="text-lg font-semibold text-slate-900 sm:text-xl">{{ $item->menuItem->name }}</p>
                                                    <p class="mt-1 text-sm text-slate-500 sm:text-base">{{ $item->quantity }} x &yen;{{ number_format((float) $item->price, 0) }}</p>
                                                </div>
                                                <p class="shrink-0 text-lg font-bold text-slate-950 sm:text-xl">&yen;{{ number_format((float) $item->price * (int) $item->quantity, 0) }}</p>
                                            </div>
                                            @if(in_array($sessionOrder->status, [OrderStatus::Pending, OrderStatus::Preparing], true))
                                                <div
                                                    class="mt-4 flex items-center justify-end gap-2"
                                                    data-reorder-id="{{ $item->menu_item_id }}"
                                                    data-reorder-name="{{ $item->menuItem->name }}"
                                                    data-reorder-price="{{ number_format((float) $item->price, 2, '.', '') }}"
                                                >
                                                    <button type="button" class="hotpepper-mini-reorder-btn" data-reorder-decrease>-1</button>
                                                    <button type="button" class="hotpepper-mini-reorder-btn" data-reorder-amount="1">+1</button>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        <div class="mx-auto grid max-w-6xl gap-6 px-4 py-5 sm:px-6 md:grid-cols-[minmax(0,1fr)_340px] lg:grid-cols-[220px_minmax(0,1fr)_370px] lg:px-8">
            <aside class="hidden lg:block lg:sticky lg:top-24 lg:self-start">
                <div class="hotpepper-category-panel customer-scroll lg:max-h-[calc(100vh-7rem)] lg:overflow-y-auto">
                    <p class="text-sm font-bold uppercase tracking-[0.24em] text-orange-600">Browse</p>
                    <h2 class="mt-1 text-xl font-black text-slate-950">Food categories</h2>
                    <div class="mt-4 space-y-3">
                        @foreach($categories as $category)
                            <a href="#category-{{ $category->id }}" class="hotpepper-drawer-link">
                                <span>{{ $category->name }}</span>
                                <span>{{ $category->menuItems->count() }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </aside>

            <div class="space-y-8">
                @foreach($categories as $category)
                    <section id="category-{{ $category->id }}" class="space-y-4 scroll-mt-28">
                        <div class="flex items-end justify-between gap-4">
                            <div>
                                <p class="text-sm font-bold uppercase tracking-[0.25em] text-orange-600">Category</p>
                                <h2 class="mt-1 text-xl font-black text-slate-950 sm:text-2xl">{{ $category->name }}</h2>
                            </div>
                            <p class="text-base text-slate-500">{{ $category->menuItems->count() }} items</p>
                        </div>

                        <div class="grid gap-4">
                            @foreach($category->menuItems as $item)
                                <article
                                    class="hotpepper-menu-card"
                                    data-menu-item
                                    data-id="{{ $item->id }}"
                                    data-name="{{ $item->name }}"
                                    data-price="{{ number_format((float) $item->price, 2, '.', '') }}"
                                >
                                    <div class="flex flex-col gap-4 sm:flex-row">
                                        <div class="hotpepper-menu-thumb">
                                            @if($item->image_url)
                                                <img src="{{ $item->image_url }}" alt="{{ $item->name }}" class="h-full w-full object-cover">
                                            @else
                                                <span>{{ strtoupper(substr($item->name, 0, 1)) }}</span>
                                            @endif
                                        </div>

                                        <div class="hotpepper-menu-content min-w-0 flex-1">
                                            <div class="hotpepper-menu-heading">
                                                <div class="min-w-0">
                                                    <div class="hotpepper-menu-title-wrap">
                                                        <h3 class="truncate text-xl font-black text-slate-950">{{ $item->name }}</h3>
                                                        <span class="hotpepper-chip">{{ $category->name }}</span>
                                                    </div>
                                                </div>
                                                <p class="hotpepper-menu-price text-xl font-black">&yen;{{ number_format((float) $item->price, 0) }}</p>
                                            </div>

                                            <div class="hotpepper-menu-footer">
                                                <div class="hotpepper-stepper" aria-label="Choose quantity">
                                                    <button type="button" class="hotpepper-stepper-btn" data-quantity-down>-</button>
                                                    <span class="min-w-8 text-center text-xl font-bold text-slate-900" data-quantity-value>0</span>
                                                    <button type="button" class="hotpepper-stepper-btn" data-quantity-up>+</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>

            <aside id="order-summary" class="hidden md:sticky md:top-24 md:block md:self-start">
                <div class="hotpepper-cart-card lg:flex lg:max-h-[calc(100vh-7rem)] lg:flex-col">
                    <div class="customer-scroll lg:min-h-0 lg:flex-1 lg:overflow-y-auto lg:pr-1">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-bold uppercase tracking-[0.25em] text-orange-600">My order</p>
                                <h2 class="mt-1 text-2xl font-black text-slate-950">Ready to send</h2>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('guest.orders.store') }}" id="guest-order-form" class="mt-6">
                            @csrf
                            <div id="guest-cart-lines" class="space-y-3">
                                <p class="rounded-2xl border border-dashed border-orange-200 bg-orange-50/70 px-4 py-4 text-lg text-slate-500 sm:text-xl" id="guest-cart-empty">
                                    Choose items from the menu to start your order.
                                </p>
                            </div>
                            <div id="guest-cart-hidden-inputs"></div>

                            <div class="mt-6 space-y-3 border-t border-orange-100 pt-4">
                                <div class="flex items-center justify-between text-lg sm:text-xl">
                                    <span class="text-slate-500">Items selected</span>
                                    <span class="font-bold text-slate-950" id="guest-cart-count">0</span>
                                </div>
                                <div class="flex items-center justify-between text-lg sm:text-xl">
                                    <span class="font-semibold text-slate-700">New order subtotal</span>
                                    <span class="text-2xl font-black text-slate-950" id="guest-cart-total">&yen;0</span>
                                </div>
                            </div>

                            <button
                                type="submit"
                                id="guest-submit-button"
                                class="mt-6 w-full rounded-2xl bg-[#b45309] px-5 py-4 text-base font-bold uppercase tracking-[0.2em] text-white shadow-[0_18px_45px_-22px_rgba(180,83,9,0.8)] transition hover:bg-[#92400e]"
                            >
                                Place order
                            </button>
                        </form>

                        @if($sessionOrders->isNotEmpty())
                            @if($hasOpenKitchenOrders)
                                <div class="mt-3 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-4 text-lg text-amber-800 sm:text-xl">
                                    Checkout can be requested now, but some items are still pending or preparing.
                                </div>
                            @endif
                            <form method="POST" action="{{ route('guest.checkout') }}" class="mt-3">
                                @csrf
                                <button
                                    type="submit"
                                    class="w-full rounded-2xl border border-orange-300 bg-orange-50 px-5 py-4 text-base font-bold uppercase tracking-[0.18em] text-orange-700 transition hover:bg-orange-100"
                                >
                                    Proceed to checkout
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </aside>
        </div>

        <aside id="guest-order-drawer" class="hotpepper-mobile-drawer hotpepper-mobile-drawer-right flex flex-col lg:hidden">
            <div class="flex items-center justify-between border-b border-orange-100 px-5 py-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.22em] text-orange-500">Review</p>
                    <h2 class="mt-1 text-xl font-black text-slate-950">Your order</h2>
                </div>
                <button type="button" data-close-drawer class="hotpepper-drawer-close">Close</button>
            </div>
            <div class="customer-scroll min-h-0 flex-1 overflow-y-auto px-4 py-4 pb-28">
                <div class="hotpepper-cart-card border-0 shadow-none">
                    <form method="POST" action="{{ route('guest.orders.store') }}" id="guest-order-form-mobile">
                        @csrf
                        <div id="guest-cart-lines-mobile" class="space-y-3">
                            <p class="rounded-2xl border border-dashed border-orange-200 bg-orange-50/70 px-4 py-4 text-lg text-slate-500 sm:text-xl" id="guest-cart-empty-mobile">
                                Choose items from the menu to start your order.
                            </p>
                        </div>
                        <div id="guest-cart-hidden-inputs-mobile"></div>

                        <div class="mt-6 space-y-3 border-t border-orange-100 pt-4">
                            <div class="flex items-center justify-between text-lg sm:text-xl">
                                <span class="text-slate-500">Items selected</span>
                                <span class="font-bold text-slate-950" id="guest-cart-count-mobile">0</span>
                            </div>
                            <div class="flex items-center justify-between text-lg sm:text-xl">
                                <span class="font-semibold text-slate-700">New order subtotal</span>
                                <span class="text-2xl font-black text-slate-950" id="guest-cart-total-mobile">&yen;0</span>
                            </div>
                        </div>

                        <button
                            type="submit"
                            id="guest-submit-button-mobile"
                            class="mt-6 w-full rounded-2xl bg-[#b45309] px-5 py-4 text-base font-bold uppercase tracking-[0.2em] text-white shadow-[0_18px_45px_-22px_rgba(180,83,9,0.8)] transition hover:bg-[#92400e]"
                        >
                            Place order
                        </button>
                    </form>

                    @if($sessionOrders->isNotEmpty())
                        @if($hasOpenKitchenOrders)
                            <div class="mt-3 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-4 text-lg text-amber-800 sm:text-xl">
                                Checkout can be requested now, but some items are still pending or preparing.
                            </div>
                        @endif
                        <form method="POST" action="{{ route('guest.checkout') }}" class="mt-3">
                            @csrf
                            <button
                                type="submit"
                                class="w-full rounded-2xl border border-orange-300 bg-orange-50 px-5 py-4 text-base font-bold uppercase tracking-[0.18em] text-orange-700 transition hover:bg-orange-100"
                            >
                                Proceed to checkout
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </aside>

        <div class="hotpepper-mobile-bar lg:hidden">
            <button type="button" id="guest-open-category" class="hotpepper-mobile-side-btn">
                <span class="hp-mobile-label font-bold uppercase tracking-[0.18em] text-orange-600">Left</span>
                <span class="hp-mobile-title mt-1 font-semibold text-slate-900">Food categories</span>
            </button>
            <button type="button" id="guest-open-order" class="hotpepper-mobile-bar-btn">
                <span class="hp-mobile-order-text" id="guest-cart-bar-total">&yen;0</span>
                <span class="hp-mobile-order-text">Order <span id="guest-cart-bar-count">0</span></span>
            </button>
        </div>
    </div>

    <script>
        (() => {
            const menuCards = document.querySelectorAll('[data-menu-item]');
            const cartLines = document.getElementById('guest-cart-lines');
            const cartLinesMobile = document.getElementById('guest-cart-lines-mobile');
            const hiddenInputs = document.getElementById('guest-cart-hidden-inputs');
            const hiddenInputsMobile = document.getElementById('guest-cart-hidden-inputs-mobile');
            const emptyState = document.getElementById('guest-cart-empty');
            const emptyStateMobile = document.getElementById('guest-cart-empty-mobile');
            const countEl = document.getElementById('guest-cart-count');
            const countMobileEl = document.getElementById('guest-cart-count-mobile');
            const totalEl = document.getElementById('guest-cart-total');
            const totalMobileEl = document.getElementById('guest-cart-total-mobile');
            const barCountEl = document.getElementById('guest-cart-bar-count');
            const barTotalEl = document.getElementById('guest-cart-bar-total');
            const submitButton = document.getElementById('guest-submit-button');
            const submitButtonMobile = document.getElementById('guest-submit-button-mobile');
            const orderForm = document.getElementById('guest-order-form');
            const orderFormMobile = document.getElementById('guest-order-form-mobile');
            const overlay = document.getElementById('guest-mobile-overlay');
            const categoryDrawer = document.getElementById('guest-category-drawer');
            const orderDrawer = document.getElementById('guest-order-drawer');
            const categoryToggle = document.getElementById('guest-open-category');
            const orderToggle = document.getElementById('guest-open-order');
            const reorderGroups = () => document.querySelectorAll('[data-reorder-id]');
            const cart = new Map();

            const currency = (amount) => `\u00A5${Math.round(amount).toLocaleString('ja-JP')}`;

            const addToCart = (id, name, price, quantityToAdd = 1) => {
                const current = cart.get(id);
                cart.set(id, {
                    id,
                    name,
                    price: Number(price),
                    quantity: (current?.quantity ?? 0) + quantityToAdd,
                });
                render();
            };

            const decreaseCartItem = (id) => {
                const current = cart.get(id);

                if (!current) {
                    return;
                }

                if (current.quantity <= 1) {
                    cart.delete(id);
                } else {
                    cart.set(id, {
                        ...current,
                        quantity: current.quantity - 1,
                    });
                }

                render();
            };

            const openDrawer = (drawer) => {
                overlay?.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');

                if (drawer === 'category') {
                    categoryDrawer?.classList.add('is-open');
                    orderDrawer?.classList.remove('is-open');
                }

                if (drawer === 'order') {
                    orderDrawer?.classList.add('is-open');
                    categoryDrawer?.classList.remove('is-open');
                }
            };

            const closeDrawers = () => {
                overlay?.classList.add('hidden');
                categoryDrawer?.classList.remove('is-open');
                orderDrawer?.classList.remove('is-open');
                document.body.classList.remove('overflow-hidden');
            };

            const updateSubmitState = (hasItems) => {
                submitButton.disabled = !hasItems;
                submitButton.classList.toggle('opacity-50', !hasItems);
                submitButton.classList.toggle('cursor-not-allowed', !hasItems);

                submitButtonMobile.disabled = !hasItems;
                submitButtonMobile.classList.toggle('opacity-50', !hasItems);
                submitButtonMobile.classList.toggle('cursor-not-allowed', !hasItems);
            };

            const syncMenuCardQuantities = () => {
                menuCards.forEach((card) => {
                    const quantityValue = card.querySelector('[data-quantity-value]');
                    const downButton = card.querySelector('[data-quantity-down]');
                    const currentQuantity = cart.get(card.dataset.id)?.quantity ?? 0;

                    quantityValue.textContent = String(currentQuantity);
                    downButton.disabled = currentQuantity === 0;
                });
            };

            const buildCartMarkup = (entries, linesRoot, emptyNode, hiddenRoot) => {
                linesRoot.innerHTML = '';
                hiddenRoot.innerHTML = '';

                if (entries.length === 0) {
                    linesRoot.appendChild(emptyNode);
                }

                entries.forEach((entry, index) => {
                    const line = document.createElement('div');
                    line.className = 'rounded-2xl border border-orange-100 bg-white px-4 py-3';
                    line.innerHTML = `
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-[21px] font-semibold text-slate-900">${entry.name}</p>
                                <p class="mt-1 text-base text-slate-500">${entry.quantity} x ${currency(entry.price)}</p>
                                <div class="mt-3 flex items-center gap-2">
                                    <button type="button" class="hotpepper-cart-qty-btn" data-decrease-id="${entry.id}">-1</button>
                                    <button type="button" class="hotpepper-cart-qty-btn" data-increase-id="${entry.id}" data-name="${entry.name}" data-price="${entry.price}">+1</button>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-[21px] font-bold text-slate-950">${currency(entry.quantity * entry.price)}</p>
                                <button type="button" class="mt-2 text-sm font-semibold uppercase tracking-[0.15em] text-rose-500" data-remove-id="${entry.id}">Remove</button>
                            </div>
                        </div>
                    `;
                    linesRoot.appendChild(line);

                    const menuInput = document.createElement('input');
                    menuInput.type = 'hidden';
                    menuInput.name = `items[${index}][menu_item_id]`;
                    menuInput.value = entry.id;
                    hiddenRoot.appendChild(menuInput);

                    const qtyInput = document.createElement('input');
                    qtyInput.type = 'hidden';
                    qtyInput.name = `items[${index}][quantity]`;
                    qtyInput.value = entry.quantity;
                    hiddenRoot.appendChild(qtyInput);
                });
            };

            const bindCartRowActions = () => {
                document.querySelectorAll('[data-remove-id]').forEach((button) => {
                    button.addEventListener('click', () => {
                        cart.delete(button.dataset.removeId);
                        render();
                    });
                });

                document.querySelectorAll('[data-decrease-id]').forEach((button) => {
                    button.addEventListener('click', () => {
                        decreaseCartItem(button.dataset.decreaseId);
                    });
                });

                document.querySelectorAll('[data-increase-id]').forEach((button) => {
                    button.addEventListener('click', () => {
                        addToCart(
                            button.dataset.increaseId,
                            button.dataset.name,
                            button.dataset.price,
                            1
                        );
                    });
                });
            };

            const render = () => {
                const entries = [...cart.values()];
                let totalQuantity = 0;
                let totalAmount = 0;

                entries.forEach((entry) => {
                    totalQuantity += entry.quantity;
                    totalAmount += entry.quantity * entry.price;
                });

                buildCartMarkup(entries, cartLines, emptyState, hiddenInputs);
                buildCartMarkup(entries, cartLinesMobile, emptyStateMobile, hiddenInputsMobile);

                countEl.textContent = String(totalQuantity);
                countMobileEl.textContent = String(totalQuantity);
                totalEl.textContent = currency(totalAmount);
                totalMobileEl.textContent = currency(totalAmount);
                barCountEl.textContent = String(totalQuantity);
                barTotalEl.textContent = currency(totalAmount);
                updateSubmitState(entries.length > 0);
                syncMenuCardQuantities();
                bindCartRowActions();
            };

            menuCards.forEach((card) => {
                const downButton = card.querySelector('[data-quantity-down]');
                const upButton = card.querySelector('[data-quantity-up]');

                downButton.addEventListener('click', () => {
                    decreaseCartItem(card.dataset.id);
                });

                upButton.addEventListener('click', () => {
                    addToCart(card.dataset.id, card.dataset.name, card.dataset.price, 1);
                });
            });

            orderForm?.addEventListener('submit', (event) => {
                if (cart.size === 0) {
                    event.preventDefault();
                }
            });

            orderFormMobile?.addEventListener('submit', (event) => {
                if (cart.size === 0) {
                    event.preventDefault();
                }
            });

            categoryToggle?.addEventListener('click', () => openDrawer('category'));
            orderToggle?.addEventListener('click', () => openDrawer('order'));
            overlay?.addEventListener('click', closeDrawers);

            document.querySelectorAll('[data-close-drawer]').forEach((button) => {
                button.addEventListener('click', closeDrawers);
            });

            document.querySelectorAll('[data-drawer-link]').forEach((link) => {
                link.addEventListener('click', closeDrawers);
            });

            reorderGroups().forEach((container) => {
                container.querySelectorAll('[data-reorder-decrease]').forEach((button) => {
                    button.addEventListener('click', () => {
                        decreaseCartItem(container.dataset.reorderId);
                    });
                });

                container.querySelectorAll('[data-reorder-amount]').forEach((button) => {
                    button.addEventListener('click', () => {
                        addToCart(
                            container.dataset.reorderId,
                            container.dataset.reorderName,
                            container.dataset.reorderPrice,
                            Number(button.dataset.reorderAmount)
                        );
                    });
                });
            });

            render();
        })();
    </script>
@endsection
