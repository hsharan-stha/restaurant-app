<?php

namespace App\Repositories\Eloquent;

use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class OrderRepository implements OrderRepositoryInterface
{
    public function find(int $id): ?Order
    {
        return Order::query()
            ->with(['table', 'items.menuItem', 'invoice', 'payments'])
            ->find($id);
    }

    public function allWithRelations(): Collection
    {
        return Order::query()
            ->with(['table', 'items.menuItem', 'invoice', 'payments'])
            ->orderByDesc('id')
            ->get();
    }

    public function create(array $attributes): Order
    {
        return Order::query()->create($attributes);
    }

    public function update(Order $order, array $attributes): Order
    {
        $order->update($attributes);

        return $order->fresh(['table', 'items.menuItem', 'invoice', 'payments']);
    }
}
