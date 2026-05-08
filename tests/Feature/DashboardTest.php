<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\CustomerSession;
use App\Models\DiningTable;
use App\Models\Invoice;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_floating_action_panel_content(): void
    {
        $user = User::factory()->create([
            'name' => 'AdminUser',
            'email' => 'admin@example.com',
        ]);
        $adminRole = Role::query()->create(['name' => 'admin']);
        $user->roles()->attach($adminRole);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('dashboard-action-toggle')
            ->assertSee('Admin Info')
            ->assertSee('New order')
            ->assertSee('Category')
            ->assertSee('Item')
            ->assertSee('Table')
            ->assertSee('Logout');
    }

    public function test_dashboard_poll_announces_new_items_for_preparing_orders(): void
    {
        $user = User::factory()->create();
        $table = DiningTable::query()->create([
            'table_number' => 12,
            'status' => 'occupied',
        ]);
        $category = Category::query()->create(['name' => 'Kitchen']);
        $item = MenuItem::query()->create([
            'name' => 'Fried Rice',
            'price' => 14.00,
            'category_id' => $category->id,
        ]);
        $order = Order::query()->create([
            'table_id' => $table->id,
            'status' => OrderStatus::Preparing,
            'total_amount' => 14.00,
        ]);

        $baselineOrderItem = OrderItem::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $item->id,
            'quantity' => 1,
            'price' => 14.00,
        ]);

        $newOrderItem = OrderItem::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $item->id,
            'quantity' => 1,
            'price' => 14.00,
        ]);

        $this->actingAs($user)
            ->getJson(route('dashboard.poll', [
                'last_seen_id' => $order->id,
                'last_seen_order_item_id' => $baselineOrderItem->id,
            ]))
            ->assertOk()
            ->assertJsonFragment([
                'id' => $order->id,
                'table_number' => $table->table_number,
                'type' => 'preparing_order_update',
                'announcement_text' => 'Table number 12 has added an order',
            ])
            ->assertJsonFragment([
                'max_order_item_id' => $newOrderItem->id,
            ]);
    }

    public function test_dashboard_groups_completed_orders_by_customer_session(): void
    {
        $user = User::factory()->create();
        $table = DiningTable::query()->create([
            'table_number' => 8,
            'status' => 'occupied',
        ]);
        $session = CustomerSession::query()->create([
            'table_id' => $table->id,
            'session_token' => 'dashboard-group-session',
            'started_at' => now(),
            'last_seen_at' => now(),
        ]);
        $category = Category::query()->create(['name' => 'Dinner']);
        $item = MenuItem::query()->create([
            'name' => 'Curry',
            'price' => 10.00,
            'category_id' => $category->id,
        ]);

        $firstOrder = Order::query()->create([
            'table_id' => $table->id,
            'customer_session_id' => $session->id,
            'status' => OrderStatus::Completed,
            'total_amount' => 10.00,
        ]);
        $secondOrder = Order::query()->create([
            'table_id' => $table->id,
            'customer_session_id' => $session->id,
            'status' => OrderStatus::Completed,
            'total_amount' => 20.00,
        ]);

        foreach ([$firstOrder, $secondOrder] as $index => $order) {
            OrderItem::query()->create([
                'order_id' => $order->id,
                'menu_item_id' => $item->id,
                'quantity' => $index + 1,
                'price' => 10.00,
            ]);
            Invoice::query()->create([
                'order_id' => $order->id,
                'subtotal' => ($index + 1) * 10.00,
                'tax' => ($index + 1) * 0.80,
                'total' => ($index + 1) * 10.80,
            ]);
        }

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertSee('Session group')
            ->assertSee('2 orders')
            ->assertSee('Order #'.$firstOrder->id)
            ->assertSee('Order #'.$secondOrder->id)
            ->assertSee('¥32.40');
    }
}
