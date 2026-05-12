<?php

namespace Tests\Feature;

use App\Enums\DiningSessionStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Category;
use App\Models\DiningSession;
use App\Models\DiningTable;
use App\Models\Invoice;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Role;
use App\Models\User;
use App\Services\DiningSessionService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiningSessionTotalsSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_item_changes_refresh_invoice_and_dining_session_totals(): void
    {
        $table = DiningTable::query()->create([
            'table_number' => 7,
            'status' => 'occupied',
        ]);

        $session = DiningSession::query()->create([
            'table_id' => $table->id,
            'session_code' => 'SES-000007',
            'status' => DiningSessionStatus::InProgress,
            'started_at' => now(),
            'payment_status' => PaymentStatus::Pending,
            'subtotal' => 10.00,
            'tax' => 0.80,
            'grand_total' => 10.80,
        ]);

        $category = Category::query()->create(['name' => 'Main']);
        $meal = MenuItem::query()->create([
            'name' => 'Meal',
            'price' => 10.00,
            'category_id' => $category->id,
        ]);
        $side = MenuItem::query()->create([
            'name' => 'Side',
            'price' => 5.00,
            'category_id' => $category->id,
        ]);

        $order = Order::query()->create([
            'table_id' => $table->id,
            'dining_session_id' => $session->id,
            'order_number' => 1,
            'status' => OrderStatus::Completed,
            'total_amount' => 10.00,
            'ordered_at' => now(),
            'completed_at' => now(),
        ]);

        $item = OrderItem::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $meal->id,
            'quantity' => 1,
            'price' => 10.00,
            'delivered_quantity' => 1,
            'is_delivered' => true,
        ]);

        Invoice::query()->create([
            'order_id' => $order->id,
            'subtotal' => 10.00,
            'tax' => 0.80,
            'total' => 10.80,
        ]);

        /** @var DiningSessionService $diningSessionService */
        $diningSessionService = app(DiningSessionService::class);
        $diningSessionService->syncTotals($session);

        /** @var OrderService $orderService */
        $orderService = app(OrderService::class);

        $orderService->incrementOrderItem($item);

        $this->assertSame('20.00', $order->fresh()->total_amount);
        $this->assertSame('20.00', $order->fresh('invoice')->invoice->subtotal);
        $this->assertSame('1.60', $order->fresh('invoice')->invoice->tax);
        $this->assertSame('21.60', $order->fresh('invoice')->invoice->total);
        $this->assertSame('20.00', $session->fresh()->subtotal);
        $this->assertSame('21.60', $session->fresh()->grand_total);

        $orderService->addLineToPendingOrder($order->fresh(), $side->id, 1, null, []);

        $this->assertSame(OrderStatus::Preparing, $order->fresh()->status);
        $this->assertSame('25.00', $order->fresh()->total_amount);
        $this->assertSame('25.00', $order->fresh('invoice')->invoice->subtotal);
        $this->assertSame('2.00', $order->fresh('invoice')->invoice->tax);
        $this->assertSame('27.00', $order->fresh('invoice')->invoice->total);
        $this->assertSame('25.00', $session->fresh()->subtotal);
        $this->assertSame('27.00', $session->fresh()->grand_total);
    }

    public function test_increment_response_includes_refreshed_panel_totals(): void
    {
        $user = User::factory()->create();
        $staffRole = Role::query()->create(['name' => 'staff']);
        $user->roles()->attach($staffRole);

        $table = DiningTable::query()->create([
            'table_number' => 8,
            'status' => 'occupied',
        ]);

        $session = DiningSession::query()->create([
            'table_id' => $table->id,
            'session_code' => 'SES-000008',
            'status' => DiningSessionStatus::InProgress,
            'started_at' => now(),
            'payment_status' => PaymentStatus::Pending,
        ]);

        $category = Category::query()->create(['name' => 'Drinks']);
        $item = MenuItem::query()->create([
            'name' => 'Tea',
            'price' => 10.00,
            'category_id' => $category->id,
        ]);

        $order = Order::query()->create([
            'table_id' => $table->id,
            'dining_session_id' => $session->id,
            'order_number' => 1,
            'status' => OrderStatus::Completed,
            'total_amount' => 10.00,
            'ordered_at' => now(),
            'completed_at' => now(),
        ]);

        $orderItem = OrderItem::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $item->id,
            'quantity' => 1,
            'price' => 10.00,
            'delivered_quantity' => 1,
            'is_delivered' => true,
        ]);

        Invoice::query()->create([
            'order_id' => $order->id,
            'subtotal' => 10.00,
            'tax' => 0.80,
            'total' => 10.80,
        ]);

        $this->actingAs($user)
            ->postJson(route('orders.items.increment', [$order, $orderItem]))
            ->assertOk()
            ->assertJsonPath('order.total_amount', '20.00')
            ->assertJsonPath('panel.sessions.0.subtotal', '20.00')
            ->assertJsonPath('panel.sessions.0.grand_total', '21.60')
            ->assertJsonPath('panel.active_orders.0.total_amount', '20.00')
            ->assertJsonPath('panel.active_orders.0.grand_total', '21.60');
    }
}
