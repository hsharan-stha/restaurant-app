<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CheckoutRequested implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Order $order) {}

    public function broadcastOn(): array
    {
        return [new Channel('orders')];
    }

    public function broadcastAs(): string
    {
        return 'CheckoutRequested';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->order->loadMissing(['table']);
        $tableNumber = $this->order->table->table_number;

        return [
            'order' => [
                'id' => $this->order->id,
                'table' => [
                    'id' => $this->order->table->id,
                    'table_number' => $tableNumber,
                ],
                'checkout_requested_at' => $this->order->checkout_requested_at?->toIso8601String(),
            ],
            'announcement_text' => "Table number {$tableNumber} has requested checkout",
        ];
    }
}
