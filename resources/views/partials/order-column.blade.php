@php
    use App\Enums\OrderStatus;
    use App\Enums\PaymentStatus;
@endphp

<div class="flex max-h-[32rem] flex-col rounded-xl border border-slate-800 bg-slate-900/60 md:max-h-[38rem] lg:max-h-[calc(100vh-15rem)]">
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
                        <p class="mt-1 text-sm text-emerald-300">${{ number_format($order->total_amount, 2) }}</p>
                    </div>
                    <div class="flex flex-col items-end gap-1">
                        @include('partials.status-badge', ['status' => $order->status])
                        <a href="{{ route('orders.show', $order) }}" class="text-xs text-slate-400 hover:text-white">Details ></a>
                    </div>
                </div>
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
                        <a href="{{ route('payments.create', $order) }}" class="rounded-md bg-violet-700 px-2 py-1 text-xs text-white hover:bg-violet-600">Checkout</a>
                    @endif
                </div>
            </article>
        @empty
            <p class="px-2 py-6 text-center text-sm text-slate-500">No orders</p>
        @endforelse
    </div>
</div>
