<?php

namespace App\Repositories\Contracts;

use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;

interface OrderRepositoryInterface
{
    public function find(int $id): ?Order;

    public function allWithRelations(): Collection;

    public function newerThanId(int $lastSeenId): Collection;

    public function checkoutRequestedAfter(?string $lastSeenCheckoutAt): Collection;

    public function create(array $attributes): Order;

    public function update(Order $order, array $attributes): Order;
}
