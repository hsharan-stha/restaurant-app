<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewOrderCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Order $order) {}

    public function broadcastOn(): array
    {
        return [new Channel('orders')];
    }

    public function broadcastAs(): string
    {
        return 'NewOrderCreated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->order->loadMissing(['table', 'items.menuItem']);
        $tableNumber = $this->order->table->table_number;

        return [
            'order' => [
                'id' => $this->order->id,
                'status' => $this->order->status->value,
                'total_amount' => (string) $this->order->total_amount,
                'table' => [
                    'id' => $this->order->table->id,
                    'table_number' => $this->order->table->table_number,
                ],
                'items' => $this->order->items->map(fn ($item) => [
                    'quantity' => $item->quantity,
                    'price' => (string) $item->price,
                    'menu_item' => ['name' => $item->menuItem->name],
                ])->all(),
            ],
            'announcement_text' => "Table number {$tableNumber} has placed an order",
        ];
    }
}
