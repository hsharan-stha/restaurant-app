<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\DiningTable;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
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

    public function test_report_includes_checkout_done_orders_by_checkout_date(): void
    {
        $today = Carbon::parse('2026-05-10 12:00:00');

        Carbon::setTestNow($today);

        $table = DiningTable::query()->create([
            'table_number' => 99,
            'status' => 'available',
        ]);

        $order = Order::query()->create([
            'table_id' => $table->id,
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
            ->assertSee('#'.$order->id)
            ->assertSee('Paid')
            ->assertDontSee('Unpaid');

        Carbon::setTestNow();
    }

    public function test_report_shows_unpaid_for_completed_orders_without_payment(): void
    {
        $day = Carbon::parse('2026-05-11 14:00:00');
        Carbon::setTestNow($day);

        $table = DiningTable::query()->create([
            'table_number' => 5,
            'status' => 'occupied',
        ]);

        $order = Order::query()->create([
            'table_id' => $table->id,
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
            ->assertSee('#'.$order->id)
            ->assertSee('Unpaid');

        Carbon::setTestNow();
    }

    public function test_report_shows_paid_when_completed_payment_exists(): void
    {
        $day = Carbon::parse('2026-05-12 10:00:00');
        Carbon::setTestNow($day);

        $table = DiningTable::query()->create([
            'table_number' => 7,
            'status' => 'available',
        ]);

        $order = Order::query()->create([
            'table_id' => $table->id,
            'status' => OrderStatus::Completed,
            'total_amount' => 100.00,
            'completed_at' => $day,
            'updated_at' => $day,
        ]);

        Invoice::query()->create([
            'order_id' => $order->id,
            'subtotal' => 100.00,
            'tax' => 0,
            'total' => 100.00,
        ]);

        Payment::query()->create([
            'order_id' => $order->id,
            'method' => PaymentMethod::Cash,
            'status' => PaymentStatus::Completed,
        ]);

        $this->actingAs($this->admin())
            ->get(route('reporting.completed-orders', [
                'completed_from' => '2026-05-12',
                'completed_to' => '2026-05-12',
            ]))
            ->assertOk()
            ->assertSee('#'.$order->id)
            ->assertSee('Paid');

        Carbon::setTestNow();
    }

    public function test_report_includes_completed_orders_by_completed_at(): void
    {
        $day = Carbon::parse('2026-04-01 18:00:00');
        Carbon::setTestNow($day);

        $table = DiningTable::query()->create([
            'table_number' => 3,
            'status' => 'occupied',
        ]);

        $order = Order::query()->create([
            'table_id' => $table->id,
            'status' => OrderStatus::Completed,
            'total_amount' => 10.00,
            'completed_at' => $day,
            'updated_at' => $day,
        ]);

        Invoice::query()->create([
            'order_id' => $order->id,
            'subtotal' => 10.00,
            'tax' => 0,
            'total' => 10.00,
        ]);

        $this->actingAs($this->admin())
            ->get(route('reporting.completed-orders', [
                'completed_from' => '2026-04-01',
                'completed_to' => '2026-04-01',
            ]))
            ->assertOk()
            ->assertSee('#'.$order->id);

        Carbon::setTestNow();
    }
}
