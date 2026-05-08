<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\TableStatus;
use App\Events\NewOrderCreated;
use App\Models\CustomerSession;
use App\Models\DiningTable;
use App\Models\Invoice;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        protected OrderRepositoryInterface $orderRepository
    ) {}

    /**
     * @param  array<int, array{menu_item_id: int, quantity: int}>  $lines
     */
    public function createOrder(int $tableId, array $lines): Order
    {
        return $this->createOrAppendOrder($tableId, $lines);
    }

    /**
     * @param  array<int, array{menu_item_id: int, quantity: int}>  $lines
     */
    public function createOrAppendOrder(int $tableId, array $lines, ?CustomerSession $customerSession = null): Order
    {
        return DB::transaction(function () use ($customerSession, $tableId, $lines) {
            $table = DiningTable::query()->lockForUpdate()->findOrFail($tableId);
            $pendingOrder = Order::query()
                ->where('table_id', $tableId)
                ->when(
                    $customerSession,
                    fn ($query) => $query->where('customer_session_id', $customerSession->id)
                )
                ->where('status', OrderStatus::Pending->value)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $pendingOrder && ! $this->tableHasOpenGuestSession($tableId, $customerSession) && $table->status !== TableStatus::Available) {
                throw new \InvalidArgumentException('This table is not accepting new guest orders right now.');
            }

            $order = $pendingOrder ?: $this->orderRepository->create([
                'table_id' => $tableId,
                'customer_session_id' => $customerSession?->id,
                'status' => OrderStatus::Pending,
                'total_amount' => 0,
            ]);

            if ($customerSession && ! $order->customer_session_id) {
                $order->update(['customer_session_id' => $customerSession->id]);
            }

            foreach ($lines as $line) {
                $menuItem = MenuItem::query()->findOrFail($line['menu_item_id']);
                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'menu_item_id' => $menuItem->id,
                    'quantity' => (int) $line['quantity'],
                    'price' => $menuItem->price,
                ]);
            }

            $order = $this->recalculateTotal($order);

            $table->update(['status' => TableStatus::Occupied]);

            $order->load(['table', 'items.menuItem']);

            rescue(fn () => event(new NewOrderCreated($order)), report: false);

            return $order;
        });
    }

    public function updateStatus(Order $order, OrderStatus $status): Order
    {
        return DB::transaction(function () use ($order, $status) {
            $order->update(['status' => $status]);

            if ($status === OrderStatus::Completed && ! $order->invoice) {
                $this->createInvoiceForOrder($order->fresh(['items.menuItem']));
            }

            return $order->fresh(['table', 'items.menuItem', 'invoice', 'payments']);
        });
    }

    public function recalculateTotal(Order $order): Order
    {
        $order->loadMissing('items');
        $sum = $order->items->sum(fn (OrderItem $item) => (float) $item->price * (int) $item->quantity);
        $this->orderRepository->update($order, ['total_amount' => round($sum, 2)]);

        return $order->fresh(['items.menuItem']);
    }

    protected function createInvoiceForOrder(Order $order): Invoice
    {
        $taxRate = (float) config('restaurant.tax_rate', 0.08);
        $subtotal = (float) $order->total_amount;
        $tax = round($subtotal * $taxRate, 2);
        $total = round($subtotal + $tax, 2);

        return Invoice::query()->create([
            'order_id' => $order->id,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total,
        ]);
    }

    protected function tableHasOpenGuestSession(int $tableId, ?CustomerSession $customerSession): bool
    {
        if (! $customerSession) {
            return false;
        }

        return Order::query()
            ->where('table_id', $tableId)
            ->where('customer_session_id', $customerSession->id)
            ->whereIn('status', [
                OrderStatus::Pending->value,
                OrderStatus::Preparing->value,
                OrderStatus::Completed->value,
            ])
            ->exists();
    }
}
