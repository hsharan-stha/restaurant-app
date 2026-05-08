@php
    use App\Enums\PaymentStatus;
@endphp

<div class="flex h-full min-h-0 flex-col border border-slate-800 bg-slate-900/60">
    <div class="border-b border-slate-800 px-4 py-3">
        <h2 class="text-sm font-semibold uppercase tracking-wide {{ $titleClass }}">{{ $title }}</h2>
    </div>
    <div class="min-h-0 flex-1 overflow-y-auto divide-y divide-slate-800 p-2">
        @forelse($groups as $group)
            <article class="rounded-lg p-3 hover:bg-slate-800/40">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="font-medium text-white">
                            @if($group['customer_session_id'] && $group['order_count'] > 1)
                                Session group
                            @else
                                #{{ $group['orders']->first()->id }}
                            @endif
                        </p>
                        <p class="text-xs text-slate-500">Table {{ $group['table_number'] }}</p>
                        <p class="mt-1 text-xs text-slate-400">
                            {{ $group['order_count'] }} {{ \Illuminate\Support\Str::plural('order', $group['order_count']) }}
                        </p>
                        <p class="mt-1 text-sm text-emerald-300">¥{{ number_format($group['display_total'], 2) }}</p>
                    </div>
                    <div class="flex flex-col items-end gap-1">
                        @include('partials.status-badge', ['status' => $group['status']])
                    </div>
                </div>

                <div class="mt-3 rounded-md border border-slate-800 bg-slate-950/60 p-2">
                    <p class="mb-1 text-[11px] uppercase tracking-wide text-slate-500">Completed orders</p>
                    <ul class="space-y-2 text-xs text-slate-300">
                        @foreach($group['orders'] as $order)
                            @php
                                $paid = $order->payments->contains(fn ($payment) => $payment->status === PaymentStatus::Completed);
                            @endphp
                            <li class="rounded-md border border-slate-800/80 bg-slate-900/70 px-2 py-2">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="font-semibold text-white">Order #{{ $order->id }}</p>
                                        <p class="mt-1 text-[11px] text-slate-500">
                                            {{ $order->items->count() }} {{ \Illuminate\Support\Str::plural('item', $order->items->count()) }}
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-slate-300">¥{{ number_format((float) ($order->invoice->total ?? $order->total_amount), 2) }}</p>
                                        <p class="mt-1 text-[11px] {{ $paid ? 'text-emerald-300' : 'text-amber-300' }}">
                                            {{ $paid ? 'Paid' : 'Unpaid' }}
                                        </p>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    @if($group['checkout_order'])
                        <a href="{{ route('payments.create', $group['checkout_order']) }}" target="_blank" rel="noopener noreferrer" class="rounded-md bg-violet-700 px-2 py-1 text-xs text-white hover:bg-violet-600">Checkout</a>
                    @else
                        <p class="rounded-md border border-emerald-500/30 bg-emerald-950/40 px-2 py-1 text-xs text-emerald-200">Payment received</p>
                    @endif
                </div>
            </article>
        @empty
            <p class="px-2 py-6 text-center text-sm text-slate-500">No orders</p>
        @endforelse
    </div>
</div>
