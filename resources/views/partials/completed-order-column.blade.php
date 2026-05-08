@php
    use App\Enums\PaymentStatus;
@endphp

<div class="restaurant-dashboard-panel restaurant-dashboard-text flex min-h-0 flex-col border">
    <div class="border-b border-orange-200/15 px-4 py-3">
        <h2 class="text-2xl font-semibold uppercase tracking-wide {{ $titleClass }}">{{ $title }}</h2>
        <form method="GET" action="{{ route('dashboard') }}" class="mt-3 grid gap-3 sm:grid-cols-2">
            <label class="restaurant-dashboard-muted text-lg">
                <span class="restaurant-dashboard-kicker mb-1 block font-semibold uppercase tracking-[0.18em]">From</span>
                <input
                    type="date"
                    name="completed_from"
                    value="{{ $completedFilterFrom }}"
                    class="w-full rounded-lg border border-orange-200/20 bg-[#1a0d09] px-3 py-2.5 text-xl text-slate-100 focus:border-orange-300 focus:outline-none"
                >
            </label>
            <label class="restaurant-dashboard-muted text-lg">
                <span class="restaurant-dashboard-kicker mb-1 block font-semibold uppercase tracking-[0.18em]">To</span>
                <input
                    type="date"
                    name="completed_to"
                    value="{{ $completedFilterTo }}"
                    class="w-full rounded-lg border border-orange-200/20 bg-[#1a0d09] px-3 py-2.5 text-xl text-slate-100 focus:border-orange-300 focus:outline-none"
                >
            </label>
            <button type="submit" class="rounded-lg bg-orange-700 px-4 py-2.5 text-lg font-semibold uppercase tracking-[0.18em] text-white hover:bg-orange-600">
                Apply filter
            </button>
        </form>
    </div>
    <div class="restaurant-dashboard-scroll min-h-0 flex-1 space-y-8 overflow-y-auto p-3">
        @forelse($groups as $group)
            <article class="rounded-lg p-3 hover:bg-orange-200/5">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-[2rem] font-medium text-white">
                            @if($group['customer_session_id'] && $group['order_count'] > 1)
                                Session group
                            @else
                                #{{ $group['orders']->first()->id }}
                            @endif
                        </p>
                        <p class="restaurant-dashboard-muted text-xl">Table {{ $group['table_number'] }}</p>
                        <p class="restaurant-dashboard-muted mt-1 text-xl">
                            {{ $group['order_count'] }} {{ \Illuminate\Support\Str::plural('order', $group['order_count']) }}
                        </p>
                        <p class="mt-1 text-[1.7rem] text-amber-200">&yen;{{ number_format($group['display_total'], 2) }}</p>
                    </div>
                    <div class="flex flex-col items-end gap-1">
                        @include('partials.status-badge', ['status' => $group['status']])
                    </div>
                </div>

                <div class="restaurant-dashboard-inset mt-3 rounded-md border p-2">
                    <p class="restaurant-dashboard-kicker mb-1 text-lg uppercase tracking-wide">Completed orders</p>
                    <ul class="space-y-2 text-xl text-orange-50/90">
                        @foreach($group['orders'] as $order)
                            @php
                                $paid = $order->payments->contains(fn ($payment) => $payment->status === PaymentStatus::Completed);
                            @endphp
                            <li class="rounded-md border border-orange-200/10 bg-white/5 px-2 py-2">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="font-semibold text-white">Order #{{ $order->id }}</p>
                                        <p class="restaurant-dashboard-muted mt-1 text-lg">
                                            {{ $order->items->count() }} {{ \Illuminate\Support\Str::plural('item', $order->items->count()) }}
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-orange-50/90">&yen;{{ number_format((float) ($order->invoice->total ?? $order->total_amount), 2) }}</p>
                                        <p class="mt-1 text-lg {{ $paid ? 'text-emerald-300' : 'text-amber-300' }}">
                                            {{ $paid ? 'Paid' : 'Unpaid' }}
                                        </p>
                                    </div>
                                </div>
                                @if($order->items->isNotEmpty())
                                    <ul class="mt-3 space-y-2 border-t border-orange-200/10 pt-3 text-base text-orange-50/85">
                                        @foreach($order->items as $line)
                                            <li class="flex items-start justify-between gap-3">
                                                <span class="min-w-0 flex-1">{{ $line->menuItem->name }} x {{ $line->quantity }}</span>
                                                <span class="restaurant-dashboard-muted shrink-0">&yen;{{ number_format((float) $line->price * $line->quantity, 2) }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    @if($group['checkout_order'])
                        <a href="{{ route('payments.create', $group['checkout_order']) }}" target="_blank" rel="noopener noreferrer" class="rounded-md bg-rose-700 px-4 py-2.5 text-xl text-white hover:bg-rose-600">Checkout</a>
                    @else
                        <p class="rounded-md border border-emerald-500/30 bg-emerald-950/40 px-4 py-2.5 text-xl text-emerald-200">Payment received</p>
                    @endif
                </div>
            </article>
        @empty
            <p class="restaurant-dashboard-muted px-2 py-6 text-center text-xl">No completed orders for this date range</p>
        @endforelse
    </div>
</div>
