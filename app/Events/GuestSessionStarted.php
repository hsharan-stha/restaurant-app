<?php

namespace App\Events;

use App\Models\DiningTable;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GuestSessionStarted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public DiningTable $table,
        public int $partySize
    ) {}

    /**
     * @return array<int, \Illuminate\Contracts\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('dashboard')];
    }

    public function broadcastAs(): string
    {
        return 'GuestSessionStarted';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->table->loadMissing([]);

        return [
            'table_id' => $this->table->id,
            'party_size' => $this->partySize,
            'table_number' => $this->table->table_number,
            'table_name' => $this->table->table_name,
        ];
    }
}
