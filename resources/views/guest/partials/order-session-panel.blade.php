@php
    use App\Enums\OrderStatus;
@endphp

<div id="guest-order-panel" class="guest-order-panel">
    @if(!empty($hasOpenKitchenOrders) && $sessionOrders->isNotEmpty())
        <div class="mx-auto max-w-7xl px-2 pt-2 sm:px-3">
            <div class="rounded-lg border border-amber-300/90 bg-amber-50 px-3 py-2 text-[11px] font-medium leading-snug text-amber-950 sm:text-xs">
                Checkout available — some items may still be in the kitchen.
            </div>
        </div>
    @endif
    @if($sessionOrders->isNotEmpty())
        <section class="mx-auto max-w-7xl px-2 pt-2 sm:px-3 sm:pt-3">
            <div class="rounded-xl border border-orange-100 bg-white/95 p-3 shadow-sm ring-1 ring-orange-50">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-orange-600">Your session</p>
                        <p class="truncate text-sm font-bold text-slate-900">{{ $sessionOrders->count() }} orders · Table {{ $table->table_number }}</p>
                    </div>
                    <span class="shrink-0 rounded-full bg-orange-100 px-2 py-0.5 text-[10px] font-bold text-orange-900 ring-1 ring-orange-200/80">
                        {{ $activeOrder ? ucfirst(str_replace('_', ' ', $activeOrder->status->value)) : 'Idle' }}
                    </span>
                </div>

                <div class="mt-2 flex items-baseline justify-between rounded-lg bg-orange-50/80 px-2.5 py-2 ring-1 ring-orange-100/80">
                    <span class="text-[10px] font-semibold uppercase tracking-wide text-orange-800">Subtotal</span>
                    <span class="text-base font-bold tabular-nums text-slate-950">&yen;{{ number_format((float) $sessionTotal, 0) }}</span>
                </div>

                <div class="customer-scroll mt-2 flex gap-2 overflow-x-auto pb-1">
                    @foreach($sessionOrders as $sessionOrder)
                        <div class="min-w-[min(78vw,280px)] shrink-0 rounded-lg border border-orange-100 bg-[#fffaf6] p-2.5 shadow-inner ring-1 ring-orange-50/80 sm:min-w-[260px]">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-slate-900">#{{ $sessionOrder->id }}</p>
                                    <p class="text-[10px] font-medium uppercase tracking-wide text-slate-500">{{ ucwords(str_replace('_', ' ', $sessionOrder->status->value)) }}</p>
                                </div>
                                <p class="shrink-0 text-sm font-bold tabular-nums text-slate-950">&yen;{{ number_format((float) $sessionOrder->total_amount, 0) }}</p>
                            </div>
                            <div class="mt-2 space-y-1.5">
                                @foreach($sessionOrder->items as $item)
                                    <div class="rounded-md bg-white/95 px-2 py-1.5 ring-1 ring-orange-100/70">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <p class="text-xs font-semibold leading-tight text-slate-900">{{ $item->menuItem->name }}</p>
                                                <p class="text-[10px] text-slate-500">{{ $item->quantity }} × &yen;{{ number_format((float) $item->price, 0) }}</p>
                                                @if($item->notes)
                                                    <p class="mt-0.5 text-[10px] text-slate-600">“{{ mb_strlen($item->notes) > 42 ? mb_substr($item->notes, 0, 40).'…' : $item->notes }}”</p>
                                                @endif
                                                @if(is_array($item->options) && ($item->options['spice_level'] ?? null))
                                                    <p class="text-[10px] text-orange-700">{{ $item->options['spice_level'] }}</p>
                                                @endif
                                            </div>
                                            <p class="shrink-0 text-xs font-bold tabular-nums">&yen;{{ number_format((float) $item->price * (int) $item->quantity, 0) }}</p>
                                        </div>
                                        @if(in_array($sessionOrder->status, [OrderStatus::Pending, OrderStatus::Preparing], true))
                                            <div
                                                class="mt-1.5 flex justify-end gap-1"
                                                data-reorder-id="{{ $item->menu_item_id }}"
                                                data-reorder-name="{{ $item->menuItem->name }}"
                                                data-reorder-price="{{ number_format((float) $item->price, 2, '.', '') }}"
                                            >
                                                <button type="button" class="guest-reorder-chip" data-reorder-decrease>−</button>
                                                <button type="button" class="guest-reorder-chip" data-reorder-amount="1">+</button>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <a href="{{ route('guest.order-summary') }}" class="mt-2 flex w-full items-center justify-center rounded-lg border border-orange-200 bg-white py-2 text-xs font-semibold text-orange-900">
                    Summary &amp; checkout →
                </a>
            </div>
        </section>
    @else
        <div class="mx-auto max-w-7xl px-2 pt-2 text-center text-[11px] text-slate-500 sm:text-xs">
            No orders yet — tap items below.
        </div>
    @endif
</div>
