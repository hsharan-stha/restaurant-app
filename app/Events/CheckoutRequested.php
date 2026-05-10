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
        return [
            new Channel('dashboard'),
            new Channel('table.'.$this->order->table_id),
        ];
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
        $table = $this->order->table;
        $tableNumber = $table->table_number;
        $tableLabel = filled($table->table_name)
            ? (string) $table->table_name
            : 'Table number '.$tableNumber;

        return [
            'order' => [
                'id' => $this->order->id,
                'table' => [
                    'id' => $table->id,
                    'table_number' => $tableNumber,
                    'table_name' => $table->table_name,
                ],
                'checkout_requested_at' => $this->order->checkout_requested_at?->toIso8601String(),
            ],
            'announcement_text' => "{$tableLabel} has requested checkout",
        ];
    }
}
