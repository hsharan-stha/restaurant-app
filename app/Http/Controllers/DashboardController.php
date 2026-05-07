<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected OrderRepositoryInterface $orderRepository
    ) {}

    public function __invoke(): View
    {
        $orders = $this->orderRepository->allWithRelations();
        $latestCheckoutRequestAt = $orders
            ->filter(fn ($o) => $o->checkout_requested_at)
            ->max(fn ($o) => $o->checkout_requested_at?->toIso8601String());

        return view('dashboard', [
            'pendingOrders' => $orders->filter(fn ($o) => $o->status === OrderStatus::Pending)->values(),
            'preparingOrders' => $orders->filter(fn ($o) => $o->status === OrderStatus::Preparing)->values(),
            'completedOrders' => $orders->filter(fn ($o) => $o->status === OrderStatus::Completed)->values(),
            'latestCheckoutRequestAt' => $latestCheckoutRequestAt,
        ]);
    }

    public function poll(Request $request): JsonResponse
    {
        $lastSeenId = max(0, (int) $request->query('last_seen_id', 0));
        $lastSeenCheckoutAt = $request->query('last_checkout_seen_at');
        $orders = $this->orderRepository->newerThanId($lastSeenId);
        $checkoutRequests = $this->orderRepository->checkoutRequestedAfter(
            is_string($lastSeenCheckoutAt) && $lastSeenCheckoutAt !== '' ? $lastSeenCheckoutAt : null
        );

        return response()->json([
            'orders' => $orders->map(fn ($order) => [
                'id' => $order->id,
                'table_number' => $order->table?->table_number,
                'announcement_text' => sprintf('Table number %s has placed an order', $order->table?->table_number),
            ])->values(),
            'checkout_requests' => $checkoutRequests->map(fn ($order) => [
                'id' => $order->id,
                'table_number' => $order->table?->table_number,
                'checkout_requested_at' => $order->checkout_requested_at?->toIso8601String(),
                'announcement_text' => sprintf('Table number %s has requested checkout', $order->table?->table_number),
            ])->values(),
        ]);
    }
}
