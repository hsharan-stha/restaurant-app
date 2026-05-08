<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\CustomerSession;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerOrderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_start_a_table_session_and_place_an_order(): void
    {
        $table = DiningTable::query()->create([
            'table_number' => 7,
            'status' => 'available',
        ]);

        $category = Category::query()->create(['name' => 'Yakiniku']);
        $item = MenuItem::query()->create([
            'name' => 'Harami',
            'price' => 9.50,
            'category_id' => $category->id,
        ]);

        $this->get(route('guest.entry', $table))
            ->assertRedirect(route('guest.menu'))
            ->assertSessionHas('customer_session_token');

        $this->get(route('guest.menu'))
            ->assertOk()
            ->assertSee('Table 7')
            ->assertSee('Harami');

        $response = $this->post(route('guest.orders.store'), [
            'items' => [
                ['menu_item_id' => $item->id, 'quantity' => 2],
            ],
        ]);

        $order = Order::query()->first();

        $this->assertNotNull($order);
        $response->assertRedirect(route('guest.menu', ['ordered' => $order->id]));
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertEquals(19.00, (float) $order->total_amount);
        $this->assertDatabaseCount('customer_sessions', 1);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
    }

    public function test_guest_additional_orders_append_to_the_active_ticket(): void
    {
        $table = DiningTable::query()->create([
            'table_number' => 9,
            'status' => 'available',
        ]);

        $category = Category::query()->create(['name' => 'Drinks']);
        $firstItem = MenuItem::query()->create([
            'name' => 'Lemon Sour',
            'price' => 5.00,
            'category_id' => $category->id,
        ]);
        $secondItem = MenuItem::query()->create([
            'name' => 'Highball',
            'price' => 6.50,
            'category_id' => $category->id,
        ]);

        $this->get(route('guest.entry', $table));

        $this->post(route('guest.orders.store'), [
            'items' => [
                ['menu_item_id' => $firstItem->id, 'quantity' => 1],
            ],
        ]);

        $firstOrder = Order::query()->first();
        $this->assertNotNull($firstOrder);

        $this->post(route('guest.orders.store'), [
            'items' => [
                ['menu_item_id' => $secondItem->id, 'quantity' => 2],
            ],
        ])->assertRedirect(route('guest.menu', ['ordered' => $firstOrder->id]));

        $order = Order::query()->with('items')->first();

        $this->assertNotNull($order);
        $this->assertEquals(18.00, (float) $order->total_amount);
        $this->assertCount(2, $order->items);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 2);
    }

    public function test_guest_order_creates_a_new_pending_ticket_when_latest_order_is_preparing(): void
    {
        $table = DiningTable::query()->create([
            'table_number' => 11,
            'status' => 'available',
        ]);

        $customerSession = CustomerSession::query()->create([
            'table_id' => $table->id,
            'session_token' => 'session-11',
            'started_at' => now(),
            'last_seen_at' => now(),
        ]);

        $category = Category::query()->create(['name' => 'Kitchen']);
        $firstItem = MenuItem::query()->create([
            'name' => 'Ramen',
            'price' => 12.00,
            'category_id' => $category->id,
        ]);
        $secondItem = MenuItem::query()->create([
            'name' => 'Gyoza',
            'price' => 6.00,
            'category_id' => $category->id,
        ]);

        $this->withSession(['customer_session_token' => $customerSession->session_token])
            ->post(route('guest.orders.store'), [
                'items' => [
                    ['menu_item_id' => $firstItem->id, 'quantity' => 1],
                ],
            ]);

        $firstOrder = Order::query()->first();
        $this->assertNotNull($firstOrder);

        $firstOrder->update(['status' => OrderStatus::Preparing]);

        $this->withSession(['customer_session_token' => $customerSession->session_token])
            ->post(route('guest.orders.store'), [
                'items' => [
                    ['menu_item_id' => $secondItem->id, 'quantity' => 1],
                ],
            ]);

        $orders = Order::query()->with('items')->orderBy('id')->get();

        $this->assertCount(2, $orders);
        $this->assertSame(OrderStatus::Preparing, $orders[0]->status);
        $this->assertSame(OrderStatus::Pending, $orders[1]->status);
        $this->assertCount(1, $orders[0]->items);
        $this->assertCount(1, $orders[1]->items);
    }

    public function test_guest_can_request_checkout_for_active_order(): void
    {
        $table = DiningTable::query()->create([
            'table_number' => 5,
            'status' => 'available',
        ]);

        $category = Category::query()->create(['name' => 'Dessert']);
        $item = MenuItem::query()->create([
            'name' => 'Parfait',
            'price' => 7.00,
            'category_id' => $category->id,
        ]);

        $this->get(route('guest.entry', $table));

        $this->post(route('guest.orders.store'), [
            'items' => [
                ['menu_item_id' => $item->id, 'quantity' => 1],
            ],
        ]);

        $this->post(route('guest.checkout'))
            ->assertRedirect(route('guest.menu'));

        $order = Order::query()->first();

        $this->assertNotNull($order);
        $this->assertNotNull($order->fresh()->checkout_requested_at);
    }

    public function test_guest_can_request_checkout_even_when_all_session_orders_are_completed(): void
    {
        $table = DiningTable::query()->create([
            'table_number' => 6,
            'status' => 'occupied',
        ]);

        $customerSession = CustomerSession::query()->create([
            'table_id' => $table->id,
            'session_token' => 'session-6',
            'started_at' => now(),
            'last_seen_at' => now(),
        ]);

        $order = Order::query()->create([
            'table_id' => $table->id,
            'customer_session_id' => $customerSession->id,
            'status' => OrderStatus::Completed,
            'total_amount' => 12.00,
        ]);

        $this->withSession(['customer_session_token' => $customerSession->session_token])
            ->post(route('guest.checkout'))
            ->assertRedirect(route('guest.menu'));

        $this->assertNotNull($order->fresh()->checkout_requested_at);
    }

    public function test_guest_menu_displays_multiple_orders_from_same_session(): void
    {
        $table = DiningTable::query()->create([
            'table_number' => 10,
            'status' => 'occupied',
        ]);

        $customerSession = CustomerSession::query()->create([
            'table_id' => $table->id,
            'session_token' => 'session-10',
            'started_at' => now(),
            'last_seen_at' => now(),
        ]);

        $category = Category::query()->create(['name' => 'Dinner']);
        $item = MenuItem::query()->create([
            'name' => 'Sushi',
            'price' => 8.00,
            'category_id' => $category->id,
        ]);

        $firstOrder = Order::query()->create([
            'table_id' => $table->id,
            'customer_session_id' => $customerSession->id,
            'status' => OrderStatus::Preparing,
            'total_amount' => 8.00,
        ]);
        $secondOrder = Order::query()->create([
            'table_id' => $table->id,
            'customer_session_id' => $customerSession->id,
            'status' => OrderStatus::Completed,
            'total_amount' => 16.00,
        ]);

        foreach ([$firstOrder, $secondOrder] as $index => $order) {
            $order->items()->create([
                'menu_item_id' => $item->id,
                'quantity' => $index + 1,
                'price' => 8.00,
            ]);
        }

        $this->withSession(['customer_session_token' => $customerSession->session_token])
            ->get(route('guest.menu'))
            ->assertOk()
            ->assertSee('2 orders placed')
            ->assertSee('Order #'.$firstOrder->id)
            ->assertSee('Order #'.$secondOrder->id)
            ->assertSee('Checkout can be requested now, but some items are still pending or preparing.');
    }

    public function test_guest_cannot_start_different_session_on_already_occupied_table(): void
    {
        $table = DiningTable::query()->create([
            'table_number' => 1,
            'status' => 'occupied',
        ]);

        CustomerSession::query()->create([
            'table_id' => $table->id,
            'session_token' => 'existing-session',
            'started_at' => now(),
            'last_seen_at' => now(),
        ]);

        $this->get(route('guest.entry', $table))
            ->assertOk()
            ->assertSee('Table 1 is occupied')
            ->assertSee('Table 1 is already occupied in different session.');
    }
}
