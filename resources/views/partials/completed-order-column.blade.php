@php
    use App\Enums\PaymentStatus;
@endphp

<div class="restaurant-dashboard-panel restaurant-dashboard-text flex min-h-0 flex-col border-2 border-emerald-500/30">
    <div class="border-b-2 border-emerald-500/30 bg-gradient-to-r from-emerald-950/40 to-emerald-900/20 px-4 py-3">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-2xl font-semibold uppercase tracking-wide {{ $titleClass }}">{{ $title }}</h2>
            <a href="{{ route('reporting.completed-orders') }}" target="_blank" rel="noopener noreferrer" class="rounded-lg border border-emerald-400/50 bg-emerald-900/30 px-3 py-1.5 text-sm text-emerald-300 hover:border-emerald-300 hover:bg-emerald-900/50">
                View Reports
            </a>
        </div>
    </div>
    <div class="restaurant-dashboard-scroll min-h-0 flex-1 space-y-8 overflow-y-auto p-3">
        @forelse($groups as $group)
            <article class="rounded-lg border-2 border-emerald-500/40 bg-emerald-950/30 p-4 hover:border-emerald-500/60 hover:bg-emerald-950/50 shadow-lg">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-5xl font-bold text-white">Table {{ $group['table_number'] }}</p>
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
                            <li class="rounded-md border-2 border-emerald-400/40 bg-emerald-900/20 px-3 py-3">
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
                                    <ul class="mt-3 space-y-2 border-t-2 border-emerald-400/40 pt-3 text-base text-orange-50/85">
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

                <div class="mt-3 border-t-2 border-emerald-400/40 pt-3 flex flex-wrap gap-2">
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
