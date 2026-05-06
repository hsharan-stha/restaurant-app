<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected OrderRepositoryInterface $orderRepository
    ) {}

    public function __invoke(): View
    {
        $orders = $this->orderRepository->allWithRelations();

        return view('dashboard', [
            'pendingOrders' => $orders->filter(fn ($o) => $o->status === OrderStatus::Pending)->values(),
            'preparingOrders' => $orders->filter(fn ($o) => $o->status === OrderStatus::Preparing)->values(),
            'completedOrders' => $orders->filter(fn ($o) => $o->status === OrderStatus::Completed)->values(),
        ]);
    }
}
