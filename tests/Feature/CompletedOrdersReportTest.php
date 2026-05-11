<?php

namespace Tests\Feature;

use App\Enums\DiningSessionStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\DiningSession;
use App\Models\DiningTable;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompletedOrdersReportTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $user = User::factory()->create();
        $adminRole = Role::query()->create(['name' => 'admin']);
        $user->roles()->attach($adminRole);

        return $user;
    }

    public function test_report_lists_checked_out_dining_sessions_in_range(): void
    {
        $today = Carbon::parse('2026-05-10 12:00:00');

        Carbon::setTestNow($today);

        $table = DiningTable::query()->create([
            'table_number' => 99,
            'status' => 'available',
        ]);

        $session = DiningSession::query()->create([
            'table_id' => $table->id,
            'session_code' => 'SES-000101',
            'status' => DiningSessionStatus::CheckedOut,
            'started_at' => $today->copy()->subHours(2),
            'closed_at' => $today->copy()->subMinute(),
            'subtotal' => 25.50,
            'tax' => 0,
            'discount' => 0,
            'grand_total' => 25.50,
            'payment_status' => PaymentStatus::Completed,
        ]);

        $order = Order::query()->create([
            'table_id' => $table->id,
            'dining_session_id' => $session->id,
            'status' => OrderStatus::CheckoutDone,
            'total_amount' => 25.50,
            'completed_at' => $today->copy()->subHour(),
            'checkout_at' => $today->copy()->subMinute(),
            'updated_at' => $today->copy()->subMinute(),
        ]);

        Invoice::query()->create([
            'order_id' => $order->id,
            'subtotal' => 25.50,
            'tax' => 0,
            'total' => 25.50,
        ]);

        $this->actingAs($this->admin())
            ->get(route('reporting.completed-orders', [
                'completed_from' => '2026-05-10',
                'completed_to' => '2026-05-10',
            ]))
            ->assertOk()
            ->assertSee('SES-000101')
            ->assertSee('T99')
            ->assertSee('Print');

        Carbon::setTestNow();
    }

    public function test_report_excludes_open_sessions(): void
    {
        $day = Carbon::parse('2026-05-11 14:00:00');
        Carbon::setTestNow($day);

        $table = DiningTable::query()->create([
            'table_number' => 5,
            'status' => 'occupied',
        ]);

        $session = DiningSession::query()->create([
            'table_id' => $table->id,
            'session_code' => 'SES-OPEN01',
            'status' => DiningSessionStatus::Open,
            'started_at' => $day,
            'subtotal' => 42.00,
            'tax' => 0,
            'discount' => 0,
            'grand_total' => 42.00,
            'payment_status' => PaymentStatus::Pending,
        ]);

        $order = Order::query()->create([
            'table_id' => $table->id,
            'dining_session_id' => $session->id,
            'status' => OrderStatus::Completed,
            'total_amount' => 42.00,
            'completed_at' => $day,
            'updated_at' => $day,
        ]);

        Invoice::query()->create([
            'order_id' => $order->id,
            'subtotal' => 42.00,
            'tax' => 0,
            'total' => 42.00,
        ]);

        $this->actingAs($this->admin())
            ->get(route('reporting.completed-orders', [
                'completed_from' => '2026-05-11',
                'completed_to' => '2026-05-11',
            ]))
            ->assertOk()
            ->assertDontSee('SES-OPEN01')
            ->assertSee('No completed sessions in this range.');

        Carbon::setTestNow();
    }
}
