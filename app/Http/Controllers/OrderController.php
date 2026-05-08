<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Order;
use App\Repositories\Contracts\MenuItemRepositoryInterface;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
        protected MenuItemRepositoryInterface $menuItemRepository
    ) {}

    public function create(): View
    {
        $tables = DiningTable::query()->orderBy('table_number')->get();
        $menuItems = $this->menuItemRepository->allWithCategories();

        return view('orders.create', compact('tables', 'menuItems'));
    }

    public function store(StoreOrderRequest $request): RedirectResponse
    {
        try {
            $order = $this->orderService->createOrder(
                (int) $request->validated('table_id'),
                $request->validated('items')
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['table_id' => $e->getMessage()])->withInput();
        }

        return redirect()->route('orders.show', $order)->with('status', 'Order placed.');
    }

    public function show(Order $order): View
    {
        $order->load(['table', 'items.menuItem', 'invoice', 'payments']);

        return view('orders.show', compact('order'));
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): RedirectResponse
    {
        $status = OrderStatus::from($request->validated('status'));
        $this->orderService->updateStatus($order, $status);

        return redirect()->route('dashboard');
    }
}
