<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\SessionStatus;
use App\Enums\TableStatus;
use App\Events\CheckoutCompletedEvent;
use App\Events\OrderCompletedEvent;
use App\Events\OrderPlacedEvent;
use App\Events\OrderPreparingEvent;
use App\Events\OrderUpdated;
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

            $staffWalkIn = $customerSession === null;

            if (! $pendingOrder && ! $this->tableHasOpenGuestSession($tableId, $customerSession) && $table->status !== TableStatus::Available) {
                if (! $staffWalkIn) {
                    throw new \InvalidArgumentException('This table is not accepting new guest orders right now.');
                }
            }

            $nextOrderNum = (int) (Order::query()->where('table_id', $tableId)->max('order_number') ?? 0) + 1;

            $order = $pendingOrder ?: $this->orderRepository->create([
                'table_id' => $tableId,
                'customer_session_id' => $customerSession?->id,
                'order_number' => $nextOrderNum,
                'status' => OrderStatus::Pending,
                'total_amount' => 0,
                'ordered_at' => now(),
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
                    'notes' => isset($line['notes']) ? (string) $line['notes'] : null,
                    'options' => isset($line['options']) && is_array($line['options']) ? $line['options'] : null,
                ]);
            }

            $order = $this->recalculateTotal($order);

            $table->update(['status' => TableStatus::Occupied]);

            $order->load(['table', 'items.menuItem']);

            rescue(fn () => event(new OrderPlacedEvent($order)), report: false);

            return $order;
        });
    }

    public function updateStatus(Order $order, OrderStatus $status): Order
    {
        if ($status === OrderStatus::CheckoutDone) {
            return $this->transitionOrderToCheckoutDone($order);
        }

        return DB::transaction(function () use ($order, $status) {
            $payload = ['status' => $status];
            if ($status === OrderStatus::Completed) {
                $payload['completed_at'] = now();
            }

            $order->update($payload);

            if ($status === OrderStatus::Completed && ! $order->invoice) {
                $this->createInvoiceForOrder($order->fresh(['items.menuItem']));
            }

            $fresh = $order->fresh(['table', 'items.menuItem', 'invoice', 'payments']);

            rescue(fn () => event(new OrderUpdated($fresh, null)), report: false);

            if ($status === OrderStatus::Preparing) {
                rescue(fn () => event(new OrderPreparingEvent($fresh)), report: false);
            }
            if ($status === OrderStatus::Completed) {
                rescue(fn () => event(new OrderCompletedEvent($fresh)), report: false);
            }

            return $fresh;
        });
    }

    /**
     * Completed → Checkout Done: closes bill lifecycle, frees table & session when nothing else is open.
     */
    public function transitionOrderToCheckoutDone(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            abort_unless(
                $order->status === OrderStatus::Completed,
                422,
                'Only orders that are completed (food served) can be checked out.'
            );

            if (! $order->invoice) {
                $this->createInvoiceForOrder($order->fresh(['items.menuItem']));
                $order->refresh();
            }

            $order->update([
                'status' => OrderStatus::CheckoutDone,
                'checkout_at' => now(),
            ]);

            $fresh = $order->fresh(['table', 'items.menuItem', 'invoice', 'payments', 'customerSession']);

            $this->releaseTableAndCloseSessionIfIdle($fresh);

            rescue(fn () => event(new OrderUpdated($fresh, null)), report: false);
            rescue(fn () => event(new CheckoutCompletedEvent($fresh)), report: false);

            return $fresh;
        });
    }

    /**
     * Used after payment is recorded: same lifecycle as manual checkout.
     */
    public function transitionPaidOrderToCheckoutDone(Order $order): Order
    {
        return $this->transitionOrderToCheckoutDone($order);
    }

    protected function releaseTableAndCloseSessionIfIdle(Order $order): void
    {
        $tableId = $order->table_id;
        $sessionId = $order->customer_session_id;

        $activeQuery = Order::query()
            ->where('table_id', $tableId)
            ->whereIn('status', [
                OrderStatus::Pending->value,
                OrderStatus::Preparing->value,
                OrderStatus::Completed->value,
            ]);

        if ($sessionId) {
            $activeQuery->where('customer_session_id', $sessionId);
        }

        if ($activeQuery->exists()) {
            return;
        }

        DiningTable::query()->whereKey($tableId)->update(['status' => TableStatus::Available]);

        if (! $sessionId) {
            return;
        }

        $session = CustomerSession::query()->lockForUpdate()->find($sessionId);
        if (! $session || $session->status === SessionStatus::Completed) {
            return;
        }

        $bill = Order::query()
            ->where('customer_session_id', $sessionId)
            ->where('status', OrderStatus::CheckoutDone->value)
            ->with('invoice')
            ->get()
            ->sum(fn (Order $o) => (float) ($o->invoice?->total ?? $o->total_amount));

        $session->update([
            'closed_at' => now(),
            'status' => SessionStatus::Completed,
            'total_bill' => round($bill, 2),
        ]);
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

        if ($customerSession->table_id !== $tableId) {
            return false;
        }

        if ($customerSession->status !== SessionStatus::Active) {
            return false;
        }

        return true;
    }

    public function orderPanelPayload(Order $order): array
    {
        $order->loadMissing(['items.menuItem']);
        $taxRate = (float) config('restaurant.tax_rate', 0);
        $subtotal = (float) $order->total_amount;
        $taxAmount = round($subtotal * $taxRate, 2);
        $grandTotal = round($subtotal + $taxAmount, 2);

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status->value,
            'total_amount' => (string) $order->total_amount,
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'tax_rate' => $taxRate,
            'tax_amount' => number_format($taxAmount, 2, '.', ''),
            'grand_total' => number_format($grandTotal, 2, '.', ''),
            'editable' => $order->status === OrderStatus::Pending,
            'ordered_at' => $order->ordered_at?->toIso8601String(),
            'completed_at' => $order->completed_at?->toIso8601String(),
            'checkout_at' => $order->checkout_at?->toIso8601String(),
            'created_at' => $order->created_at?->toIso8601String(),
            'updated_at' => $order->updated_at?->toIso8601String(),
            'items' => $order->items->sortBy('id')->values()->map(fn (OrderItem $item) => [
                'id' => $item->id,
                'menu_item_id' => $item->menu_item_id,
                'name' => $item->menuItem->name,
                'quantity' => $item->quantity,
                'price' => (string) $item->price,
                'line_total' => number_format((float) $item->price * (int) $item->quantity, 2, '.', ''),
                'notes' => $item->notes,
                'options' => $item->options ?? [],
            ])->all(),
        ];
    }

    /** JSON shape returned after pending line mutations (matches drawer payload). */
    public function serializeOrderForApi(Order $order): array
    {
        return $this->orderPanelPayload($order->fresh(['items.menuItem', 'table']));
    }

    public function incrementOrderItem(OrderItem $item): Order
    {
        return DB::transaction(function () use ($item) {
            $item = OrderItem::query()->lockForUpdate()->findOrFail($item->id);
            $order = Order::query()->lockForUpdate()->findOrFail($item->order_id);
            $this->ensurePending($order);
            $item->update(['quantity' => $item->quantity + 1]);
            $order = $this->recalculateTotal($order->fresh());
            $order->load(['table', 'items.menuItem']);
            rescue(fn () => event(new OrderUpdated($order, $this->voiceLineForOrder($order))), report: false);

            return $order;
        });
    }

    public function decrementOrderItem(OrderItem $item): Order
    {
        return DB::transaction(function () use ($item) {
            $item = OrderItem::query()->lockForUpdate()->findOrFail($item->id);
            $order = Order::query()->lockForUpdate()->findOrFail($item->order_id);
            $this->ensurePending($order);
            if ((int) $item->quantity <= 1) {
                $orderId = $item->order_id;
                $item->delete();
                $order = $this->recalculateTotal(Order::query()->findOrFail($orderId));
            } else {
                $item->update(['quantity' => $item->quantity - 1]);
                $order = $this->recalculateTotal($order->fresh());
            }
            $order->load(['table', 'items.menuItem']);
            rescue(fn () => event(new OrderUpdated($order, $this->voiceLineForOrder($order))), report: false);

            return $order;
        });
    }

    public function removeOrderItem(OrderItem $item): Order
    {
        return DB::transaction(function () use ($item) {
            $item = OrderItem::query()->lockForUpdate()->findOrFail($item->id);
            $order = Order::query()->lockForUpdate()->findOrFail($item->order_id);
            $this->ensurePending($order);
            $item->delete();
            $order = $this->recalculateTotal($order->fresh());
            $order->load(['table', 'items.menuItem']);
            rescue(fn () => event(new OrderUpdated($order, $this->voiceLineForOrder($order))), report: false);

            return $order;
        });
    }

    /**
     * @param  array<string, mixed>  $options  spice_level, toppings, etc.
     */
    public function addLineToPendingOrder(Order $order, int $menuItemId, int $quantity, ?string $notes, array $options): Order
    {
        return DB::transaction(function () use ($order, $menuItemId, $quantity, $notes, $options) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $this->ensurePending($order);
            $menuItem = MenuItem::query()->findOrFail($menuItemId);
            OrderItem::query()->create([
                'order_id' => $order->id,
                'menu_item_id' => $menuItem->id,
                'quantity' => $quantity,
                'price' => $menuItem->price,
                'notes' => $notes,
                'options' => $options !== [] ? $options : null,
            ]);
            $order = $this->recalculateTotal($order);
            $order->load(['table', 'items.menuItem']);
            rescue(fn () => event(new OrderUpdated($order, $this->voiceLineForOrder($order))), report: false);

            return $order;
        });
    }

    /**
     * @param  array<string, mixed>  $patch  notes, options
     */
    public function updatePendingOrderItem(OrderItem $item, array $patch): Order
    {
        return DB::transaction(function () use ($item, $patch) {
            $item = OrderItem::query()->lockForUpdate()->findOrFail($item->id);
            $order = Order::query()->lockForUpdate()->findOrFail($item->order_id);
            $this->ensurePending($order);

            if (array_key_exists('notes', $patch)) {
                $item->notes = $patch['notes'];
            }
            if (array_key_exists('options', $patch) && is_array($patch['options'])) {
                $item->options = array_merge($item->options ?? [], $patch['options']);
            }
            $item->save();

            $order = $order->fresh(['table', 'items.menuItem']);
            rescue(fn () => event(new OrderUpdated($order, $this->voiceLineForOrder($order))), report: false);

            return $order;
        });
    }

    protected function ensurePending(Order $order): void
    {
        abort_unless($order->status === OrderStatus::Pending, 422, 'Order is not pending. Editing is locked once the kitchen starts preparing.');
    }

    protected function voiceLineForOrder(Order $order): string
    {
        $order->loadMissing('table');
        $t = $order->table;
        $name = trim((string) ($t?->table_name ?? ''));
        $label = $name !== '' ? $name : ('Table '.($t?->table_number ?? ''));

        return "Order updated for {$label}.";
    }
}
