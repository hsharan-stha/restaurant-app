<?php

namespace App\Repositories\Eloquent;

use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class OrderRepository implements OrderRepositoryInterface
{
    public function find(int $id): ?Order
    {
        return Order::query()
            ->with(['table', 'customerSession', 'diningSession', 'items.menuItem', 'invoice', 'payments'])
            ->find($id);
    }

    public function allWithRelations(): Collection
    {
        return Order::query()
            ->with(['table', 'customerSession', 'diningSession', 'items.menuItem', 'invoice', 'payments'])
            ->orderByDesc('id')
            ->get();
    }

    public function newerThanId(int $lastSeenId): Collection
    {
        return Order::query()
            ->with(['table', 'customerSession', 'diningSession', 'items.menuItem', 'invoice', 'payments'])
            ->where('id', '>', $lastSeenId)
            ->orderBy('id')
            ->get();
    }

    public function checkoutRequestedAfter(?string $lastSeenCheckoutAt): Collection
    {
        return Order::query()
            ->with(['table', 'customerSession', 'diningSession', 'items.menuItem', 'invoice', 'payments'])
            ->whereNotNull('checkout_requested_at')
            ->when(
                $lastSeenCheckoutAt,
                fn (Builder $query) => $query->where('checkout_requested_at', '>', $lastSeenCheckoutAt)
            )
            ->orderBy('checkout_requested_at')
            ->get();
    }

    public function create(array $attributes): Order
    {
        return Order::query()->create($attributes);
    }

    public function update(Order $order, array $attributes): Order
    {
        $order->update($attributes);

        return $order->fresh(['table', 'customerSession', 'diningSession', 'items.menuItem', 'invoice', 'payments']);
    }
}
