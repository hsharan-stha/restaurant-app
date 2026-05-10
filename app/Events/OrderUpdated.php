<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order,
        public ?string $voiceLine = null,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('dashboard'),
            new Channel('table.'.$this->order->table_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'OrderUpdated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->order->loadMissing(['table']);

        return [
            'order' => [
                'id' => $this->order->id,
                'status' => $this->order->status->value,
                'table_id' => $this->order->table_id,
                'table_name' => $this->order->table?->table_name,
                'table_number' => $this->order->table?->table_number,
            ],
            'voice_line' => $this->voiceLine,
        ];
    }
}
