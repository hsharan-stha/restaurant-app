<?php

namespace Tests\Feature;

use App\Enums\DiningSessionStatus;
use App\Enums\OrderStatus;
use App\Enums\SessionStatus;
use App\Enums\TableStatus;
use App\Models\Category;
use App\Models\CustomerSession;
use App\Models\DiningSession;
use App\Models\DiningTable;
use App\Models\Invoice;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
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
            ->assertSee('dashboard-floor-root')
            ->assertSee('df-konva-container')
            ->assertSee('Restaurant dashboard')
            ->assertSee('df-actions-menu-btn')
            ->assertSee('New order')
            ->assertSee('Edit floor plan')
            ->assertSee('Logout');
    }

    public function test_staff_dashboard_actions_menu_excludes_admin_only_links(): void
    {
        $user = User::factory()->create();
        $staffRole = Role::query()->create(['name' => 'staff']);
        $user->roles()->attach($staffRole);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertSee('df-actions-menu-btn')
            ->assertSee('New order')
            ->assertDontSee('Edit floor plan')
            ->assertDontSee('Item sales matrix');

        $html = $response->getContent();
        $this->assertStringNotContainsString(route('reporting.completed-orders'), $html);
        $this->assertStringNotContainsString(route('menu-items.index'), $html);
    }

    public function test_latest_orders_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/latest-orders')->assertUnauthorized();
    }

    public function test_latest_orders_bootstrap_returns_empty_new_orders(): void
    {
        $user = User::factory()->create();
        $staffRole = Role::query()->create(['name' => 'staff']);
        $user->roles()->attach($staffRole);

        $this->actingAs($user)
            ->getJson('/api/latest-orders')
            ->assertOk()
            ->assertJsonPath('new_orders', [])
            ->assertJsonStructure([
                'latest_order_id',
                'new_orders',
                'recent_orders',
                'live_order_count',
                'pending_order_count',
                'unread_count',
            ]);
    }

    public function test_latest_orders_returns_new_pending_orders_after_reference_id(): void
    {
        $user = User::factory()->create();
        $staffRole = Role::query()->create(['name' => 'staff']);
        $user->roles()->attach($staffRole);

        $table = DiningTable::query()->create([
            'table_number' => 44,
            'table_name' => 'Patio',
            'status' => 'occupied',
        ]);

        $order = Order::query()->create([
            'table_id' => $table->id,
            'status' => OrderStatus::Pending,
            'total_amount' => 12.00,
            'ordered_at' => now(),
        ]);

        $this->actingAs($user)
            ->getJson('/api/latest-orders?after_order_id='.($order->id - 1))
            ->assertOk()
            ->assertJsonFragment([
                'id' => $order->id,
                'status' => OrderStatus::Pending->value,
                'table_label' => 'Patio',
            ]);
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

        $response = $this->actingAs($user)->getJson(route('dashboard.floor.table.panel', $table));

        $response
            ->assertOk()
            ->assertJsonPath('sessions.0.customer_session_id', $session->id);

        $payload = $response->json();
        $this->assertCount(1, $payload['sessions']);
        $sessionOrders = $payload['sessions'][0]['orders'];
        $this->assertCount(2, $sessionOrders);
        $ids = collect($sessionOrders)->pluck('id')->all();
        $this->assertContains($firstOrder->id, $ids);
        $this->assertContains($secondOrder->id, $ids);
        $response->assertJsonFragment(['total_amount' => '10.00']);
        $response->assertJsonFragment(['total_amount' => '20.00']);
    }

    public function test_dashboard_panel_includes_empty_active_sessions_without_orders(): void
    {
        $user = User::factory()->create();
        $staffRole = Role::query()->create(['name' => 'staff']);
        $user->roles()->attach($staffRole);

        $table = DiningTable::query()->create([
            'table_number' => 15,
            'status' => TableStatus::Occupied,
        ]);

        $customerSession = CustomerSession::query()->create([
            'table_id' => $table->id,
            'session_token' => 'empty-session-token',
            'party_size' => 2,
            'started_at' => now(),
            'last_seen_at' => now(),
            'status' => SessionStatus::Active,
        ]);

        $diningSession = DiningSession::query()->create([
            'table_id' => $table->id,
            'session_code' => 'SES-EMPTY1',
            'status' => DiningSessionStatus::Open,
            'started_at' => now(),
            'subtotal' => 0,
            'tax' => 0,
            'grand_total' => 0,
            'payment_status' => 'pending',
        ]);

        $this->actingAs($user)
            ->getJson(route('dashboard.floor.table.panel', $table))
            ->assertOk()
            ->assertJsonPath('active_customer_session.id', $customerSession->id)
            ->assertJsonPath('active_dining_session.id', $diningSession->id)
            ->assertJsonPath('active_dining_session.session_code', 'SES-EMPTY1');
    }

    public function test_staff_can_clear_empty_table_session(): void
    {
        $user = User::factory()->create();
        $staffRole = Role::query()->create(['name' => 'staff']);
        $user->roles()->attach($staffRole);

        $table = DiningTable::query()->create([
            'table_number' => 21,
            'status' => TableStatus::Occupied,
        ]);

        $customerSession = CustomerSession::query()->create([
            'table_id' => $table->id,
            'session_token' => 'clear-empty-session-token',
            'party_size' => 2,
            'started_at' => now(),
            'last_seen_at' => now(),
            'status' => SessionStatus::Active,
        ]);

        $diningSession = DiningSession::query()->create([
            'table_id' => $table->id,
            'session_code' => 'SES-CLEAR1',
            'status' => DiningSessionStatus::Open,
            'started_at' => now(),
            'subtotal' => 0,
            'tax' => 0,
            'grand_total' => 0,
            'payment_status' => 'pending',
        ]);

        $this->actingAs($user)
            ->postJson(route('dining-tables.clear-session', $table))
            ->assertOk()
            ->assertJsonPath('message', 'Session cleared and table marked available.');

        $this->assertSame(SessionStatus::Completed, $customerSession->fresh()->status);
        $this->assertSame(DiningSessionStatus::Cancelled, $diningSession->fresh()->status);
        $this->assertSame(TableStatus::Available, $table->fresh()->status);
    }

    public function test_staff_cannot_clear_session_once_orders_exist(): void
    {
        $user = User::factory()->create();
        $staffRole = Role::query()->create(['name' => 'staff']);
        $user->roles()->attach($staffRole);

        $table = DiningTable::query()->create([
            'table_number' => 22,
            'status' => TableStatus::Occupied,
        ]);

        $customerSession = CustomerSession::query()->create([
            'table_id' => $table->id,
            'session_token' => 'clear-blocked-session-token',
            'party_size' => 2,
            'started_at' => now(),
            'last_seen_at' => now(),
            'status' => SessionStatus::Active,
        ]);

        $diningSession = DiningSession::query()->create([
            'table_id' => $table->id,
            'session_code' => 'SES-BLOCK1',
            'status' => DiningSessionStatus::InProgress,
            'started_at' => now(),
            'subtotal' => 10,
            'tax' => 0.8,
            'grand_total' => 10.8,
            'payment_status' => 'pending',
        ]);

        Order::query()->create([
            'table_id' => $table->id,
            'customer_session_id' => $customerSession->id,
            'dining_session_id' => $diningSession->id,
            'status' => OrderStatus::Pending,
            'total_amount' => 10.00,
        ]);

        $this->actingAs($user)
            ->postJson(route('dining-tables.clear-session', $table))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['session']);

        $this->assertSame(SessionStatus::Active, $customerSession->fresh()->status);
        $this->assertSame(DiningSessionStatus::InProgress, $diningSession->fresh()->status);
        $this->assertSame(TableStatus::Occupied, $table->fresh()->status);
    }
}
