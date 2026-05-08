@php
    use App\Enums\OrderStatus;
    use App\Enums\PaymentStatus;
@endphp

<div class="flex h-full min-h-0 flex-col border border-slate-800 bg-slate-900/60">
    <div class="border-b border-slate-800 px-4 py-3">
        <h2 class="text-sm font-semibold uppercase tracking-wide {{ $titleClass }}">{{ $title }}</h2>
    </div>
    <div class="min-h-0 flex-1 overflow-y-auto divide-y divide-slate-800 p-2">
        @forelse($orders as $order)
            <article data-order-id="{{ $order->id }}" class="rounded-lg p-3 hover:bg-slate-800/40">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="font-medium text-white">#{{ $order->id }}</p>
                        <p class="text-xs text-slate-500">Table {{ $order->table->table_number }}</p>
                        @if(in_array($order->status, [OrderStatus::Pending, OrderStatus::Preparing], true))
                            @php
                                $secondsAgo = (int) $order->created_at?->diffInSeconds(now());
                                $minutesAgo = max(1, (int) ceil($secondsAgo / 60));
                                if ($secondsAgo < 45) {
                                    $orderAgeLabel = 'just now';
                                } elseif ($secondsAgo < 120) {
                                    $orderAgeLabel = 'soon';
                                } else {
                                    $orderAgeLabel = $minutesAgo.' min ago';
                                }
                                $orderAgeClass = $orderAgeLabel === 'just now'
                                    ? ($order->status === OrderStatus::Pending
                                        ? 'inline-flex rounded-full border border-rose-400/70 bg-rose-500/20 px-2 py-0.5 font-semibold text-rose-200'
                                        : 'inline-flex rounded-full border border-sky-400/60 bg-sky-500/20 px-2 py-0.5 font-semibold text-sky-200')
                                    : ($order->status === OrderStatus::Pending ? '' : 'text-sky-300');
                            @endphp
                            <p class="mt-1 text-[11px] {{ $order->status === OrderStatus::Pending ? 'text-rose-300' : 'text-sky-300' }} {{ $orderAgeClass }}">{{ $orderAgeLabel }}</p>
                        @endif
                        <p class="mt-1 text-sm text-emerald-300">¥{{ number_format($order->total_amount, 2) }}</p>
                    </div>
                    <div class="flex flex-col items-end gap-1">
                        @include('partials.status-badge', ['status' => $order->status])
                        @if($order->status !== OrderStatus::Pending)
                            <a href="{{ route('orders.show', $order) }}" target="_blank" rel="noopener noreferrer" class="text-xs text-slate-400 hover:text-white">Details ></a>
                        @endif
                    </div>
                </div>
                @if(in_array($order->status, [OrderStatus::Pending, OrderStatus::Preparing], true))
                    <div class="mt-3 rounded-md border border-slate-800 bg-slate-950/60 p-2">
                        <p class="mb-1 text-[11px] uppercase tracking-wide text-slate-500">Items</p>
                        <ul class="space-y-1 text-xs text-slate-300">
                            @foreach($order->items as $line)
                                @php
                                    $itemSecondsAgo = (int) $line->created_at?->diffInSeconds(now());
                                    $itemMinutesAgo = max(1, (int) ceil($itemSecondsAgo / 60));
                                    if ($itemSecondsAgo < 45) {
                                        $itemAgeLabel = 'just now';
                                    } elseif ($itemSecondsAgo < 120) {
                                        $itemAgeLabel = 'soon';
                                    } else {
                                        $itemAgeLabel = $itemMinutesAgo.' min ago';
                                    }
                                    $itemAgeClass = $itemAgeLabel === 'just now'
                                        ? ($order->status === OrderStatus::Pending
                                            ? 'inline-flex rounded-full border border-rose-400/70 bg-rose-500/20 px-1.5 py-0.5 font-semibold text-rose-200'
                                            : 'inline-flex rounded-full border border-sky-400/60 bg-sky-500/20 px-1.5 py-0.5 font-semibold text-sky-200')
                                        : ($order->status === OrderStatus::Pending ? 'text-rose-300' : 'text-sky-300');
                                @endphp
                                <li class="flex items-start justify-between gap-2">
                                    <span class="min-w-0 flex-1 truncate">{{ $line->menuItem->name }} x {{ $line->quantity }}</span>
                                    <span class="shrink-0 text-slate-400">¥{{ number_format((float) $line->price * $line->quantity, 2) }}</span>
                                    <span class="shrink-0 text-[10px] {{ $itemAgeClass }}">{{ $itemAgeLabel }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <div class="mt-3 flex flex-wrap gap-2">
                    @if($order->status === OrderStatus::Pending)
                        <form method="POST" action="{{ route('orders.update-status', $order) }}" class="inline">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="{{ OrderStatus::Preparing->value }}">
                            <button type="submit" class="rounded-md bg-sky-700 px-2 py-1 text-xs text-white hover:bg-sky-600">Start preparing</button>
                        </form>
                    @endif
                    @if($order->status === OrderStatus::Preparing)
                        <form method="POST" action="{{ route('orders.update-status', $order) }}" class="inline">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="{{ OrderStatus::Completed->value }}">
                            <button type="submit" class="rounded-md bg-emerald-700 px-2 py-1 text-xs text-white hover:bg-emerald-600">Mark completed</button>
                        </form>
                    @endif
                    @if($order->status === OrderStatus::Completed && $order->invoice && ! $order->payments->contains(fn ($p) => $p->status === PaymentStatus::Completed))
                        <a href="{{ route('payments.create', $order) }}" target="_blank" rel="noopener noreferrer" class="rounded-md bg-violet-700 px-2 py-1 text-xs text-white hover:bg-violet-600">Checkout</a>
                    @endif
                </div>
            </article>
        @empty
            <p class="px-2 py-6 text-center text-sm text-slate-500">No orders</p>
        @endforelse
    </div>
</div>
