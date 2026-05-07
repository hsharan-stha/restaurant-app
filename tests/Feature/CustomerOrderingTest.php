<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Category;
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

        $this->post(route('guest.orders.store'), [
            'items' => [
                ['menu_item_id' => $item->id, 'quantity' => 2],
            ],
        ])->assertRedirect(route('guest.menu', ['ordered' => 1]));

        $order = Order::query()->first();

        $this->assertNotNull($order);
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
}
