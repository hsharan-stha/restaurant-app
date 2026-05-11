@php
    use App\Enums\OrderStatus;

    $formatAgeLabel = function ($dateTime): string {
        if (! $dateTime) {
            return 'N/A';
        }

        $secondsAgo = max(0, (int) $dateTime->diffInSeconds(now()));

        if ($secondsAgo < 45) {
            return 'just now';
        }

        if ($secondsAgo < 120) {
            return 'soon';
        }

        $minutes = (int) floor($secondsAgo / 60);
        if ($minutes < 60) {
            return $minutes.' '.\Illuminate\Support\Str::plural('min', $minutes).' ago';
        }

        $hours = (int) floor($minutes / 60);
        if ($hours < 24) {
            return $hours.' '.\Illuminate\Support\Str::plural('hour', $hours).' ago';
        }

        $days = (int) floor($hours / 24);
        if ($days < 7) {
            return $days.' '.\Illuminate\Support\Str::plural('day', $days).' ago';
        }

        $weeks = (int) floor($days / 7);
        if ($weeks < 4) {
            return $weeks.' '.\Illuminate\Support\Str::plural('week', $weeks).' ago';
        }

        $months = (int) floor($days / 30);

        return $months.' '.\Illuminate\Support\Str::plural('month', $months).' ago';
    };
@endphp

<div class="restaurant-dashboard-panel restaurant-dashboard-text flex min-h-0 flex-col border-2 border-orange-500/30">
    <div class="border-b-2 border-orange-500/30 bg-gradient-to-r from-orange-950/40 to-orange-900/20 px-4 py-3">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-2xl font-semibold uppercase tracking-wide {{ $titleClass }}">{{ $title }}</h2>
            @if(!empty($showCount))
                <span class="inline-flex min-w-[3.4rem] items-center justify-center rounded-full border border-orange-200/20 bg-white/10 px-3 py-1 text-2xl font-bold text-white">
                    {{ $orders->count() }}
                </span>
            @endif
        </div>
    </div>
    <div class="restaurant-dashboard-scroll min-h-0 flex-1 space-y-8 overflow-y-auto p-3">
        @forelse($orders as $order)
            <article data-order-id="{{ $order->id }}" class="rounded-lg border-2 border-orange-500/40 bg-orange-950/30 p-4 hover:border-orange-500/60 hover:bg-orange-950/50 shadow-lg">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-5xl font-bold text-white">Table {{ $order->table->table_number }}</p>
                        @if(in_array($order->status, [OrderStatus::Pending, OrderStatus::Preparing], true))
                            @php
                                $orderAgeLabel = $formatAgeLabel($order->created_at);
                                $orderAgeClass = $orderAgeLabel === 'just now'
                                    ? ($order->status === OrderStatus::Pending
                                        ? 'inline-flex rounded-full border border-rose-400/70 bg-rose-500/20 px-2 py-0.5 font-semibold text-rose-200'
                                        : 'inline-flex rounded-full border border-sky-400/60 bg-sky-500/20 px-2 py-0.5 font-semibold text-sky-200')
                                    : ($order->status === OrderStatus::Pending ? '' : 'text-sky-300');
                            @endphp
                            <p class="mt-1 text-xl {{ $order->status === OrderStatus::Pending ? 'text-rose-300' : 'text-sky-300' }} {{ $orderAgeClass }}">{{ $orderAgeLabel }}</p>
                        @endif
                        <p class="mt-1 text-[1.7rem] text-amber-200">&yen;{{ number_format($order->total_amount, 2) }}</p>
                    </div>
                    <div class="flex flex-col items-end gap-1">
                        @include('partials.status-badge', ['status' => $order->status])
                    </div>
                </div>
                @if(in_array($order->status, [OrderStatus::Pending, OrderStatus::Preparing], true))
                    <div class="restaurant-dashboard-inset mt-3 rounded-md border-2 border-orange-400/40 bg-orange-900/20 p-3">
                        <p class="restaurant-dashboard-kicker mb-2 text-lg font-semibold uppercase tracking-wide text-orange-300">Items</p>
                        <ul class="space-y-2 text-xl text-orange-50/90">
                            @foreach($order->items as $line)
                                @php
                                    $itemAgeLabel = $formatAgeLabel($line->created_at);
                                    $itemAgeClass = $itemAgeLabel === 'just now'
                                        ? ($order->status === OrderStatus::Pending
                                            ? 'inline-flex rounded-full border border-rose-400/70 bg-rose-500/20 px-1.5 py-0.5 font-semibold text-rose-200'
                                            : 'inline-flex rounded-full border border-sky-400/60 bg-sky-500/20 px-1.5 py-0.5 font-semibold text-sky-200')
                                        : ($order->status === OrderStatus::Pending ? 'text-rose-300' : 'text-sky-300');
                                @endphp
                                <li class="flex items-start justify-between gap-2">
                                    <span class="min-w-0 flex-1 truncate">{{ $line->menuItem->name }} x {{ $line->quantity }}</span>
                                    <span class="restaurant-dashboard-muted shrink-0">&yen;{{ number_format((float) $line->price * $line->quantity, 2) }}</span>
                                    <span class="shrink-0 text-lg {{ $itemAgeClass }}">{{ $itemAgeLabel }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <div class="mt-3 border-t-2 border-orange-400/40 pt-3 flex flex-wrap gap-2">
                    @if($order->status === OrderStatus::Pending)
                        <form method="POST" action="{{ route('orders.update-status', $order) }}" class="inline">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="{{ OrderStatus::Preparing->value }}">
                            <button type="submit" class="rounded-md bg-amber-600 px-4 py-2.5 text-xl text-white hover:bg-amber-500">Start preparing</button>
                        </form>
                    @endif
                    @if($order->status === OrderStatus::Preparing)
                        <form method="POST" action="{{ route('orders.update-status', $order) }}" class="inline">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="{{ OrderStatus::Completed->value }}">
                            <button type="submit" class="rounded-md bg-orange-700 px-4 py-2.5 text-xl text-white hover:bg-orange-600">Mark completed</button>
                        </form>
                    @endif
                </div>
            </article>
        @empty
            <p class="restaurant-dashboard-muted px-2 py-6 text-center text-xl">No orders</p>
        @endforelse
    </div>
</div>
