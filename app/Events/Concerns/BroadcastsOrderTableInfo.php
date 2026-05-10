<?php

namespace App\Events\Concerns;

use App\Models\Order;

trait BroadcastsOrderTableInfo
{
    /**
     * @return array<string, mixed>
     */
    protected function tableOrderPayload(Order $order, string $orderStatus): array
    {
        $order->loadMissing('table');
        $table = $order->table;

        return [
            'table_id' => $order->table_id,
            'table_name' => $table?->table_name,
            'table_number' => $table?->table_number,
            'order_id' => $order->id,
            'order_status' => $orderStatus,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
