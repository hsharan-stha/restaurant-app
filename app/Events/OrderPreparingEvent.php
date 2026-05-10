<?php

namespace App\Events;

use App\Events\Concerns\BroadcastsOrderTableInfo;
use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderPreparingEvent implements ShouldBroadcastNow
{
    use BroadcastsOrderTableInfo, Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Order $order) {}

    /**
     * @return array<int, \Illuminate\Contracts\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('dashboard')];
    }

    public function broadcastAs(): string
    {
        return 'OrderPreparing';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->tableOrderPayload($this->order, $this->order->status->value);
    }
}
