<?php

use App\Enums\DiningSessionStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\DiningSession;
use App\Models\Order;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $orders = Order::query()
                ->whereNull('dining_session_id')
                ->orderBy('id')
                ->get();

            foreach ($orders->groupBy(fn (Order $o) => $o->customer_session_id ? 'guest-'.$o->customer_session_id : 'table-'.$o->table_id) as $groupKey => $group) {
                $first = $group->first();
                if (! $first) {
                    continue;
                }

                $status = $group->every(fn (Order $o) => $o->status === OrderStatus::CheckoutDone)
                    ? DiningSessionStatus::CheckedOut
                    : DiningSessionStatus::Open;
                $closedAt = $status === DiningSessionStatus::CheckedOut
                    ? $group->max(fn (Order $o) => $o->checkout_at ?? $o->updated_at)
                    : null;

                $session = DiningSession::query()->create([
                    'table_id' => $first->table_id,
                    'customer_name' => str_starts_with($groupKey, 'guest-') ? $groupKey : null,
                    'session_code' => 'SES-BF-'.Str::upper(Str::random(6)),
                    'status' => $status,
                    'started_at' => $group->min(fn (Order $o) => $o->ordered_at ?? $o->created_at),
                    'closed_at' => $closedAt,
                    'subtotal' => 0,
                    'tax' => 0,
                    'discount' => 0,
                    'grand_total' => 0,
                    'payment_status' => $status === DiningSessionStatus::CheckedOut ? PaymentStatus::Completed : PaymentStatus::Pending,
                ]);

                Order::query()
                    ->whereIn('id', $group->pluck('id')->all())
                    ->update(['dining_session_id' => $session->id]);
            }
        });
    }

    public function down(): void
    {
        // Keep data linkage on rollback.
    }
};
