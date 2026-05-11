<?php

namespace Tests\Feature;

use App\Enums\DiningSessionStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PreparationStatus;
use App\Models\Category;
use App\Models\DiningSession;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_delivery_updates_item_and_completion_status(): void
    {
        $user = User::factory()->create();
        $staffRole = Role::query()->create(['name' => 'staff']);
        $user->roles()->attach($staffRole);
        $table = DiningTable::query()->create([
            'table_number' => 5,
            'status' => 'occupied',
        ]);
        $session = DiningSession::query()->create([
            'table_id' => $table->id,
            'session_code' => 'SES-555555',
            'status' => DiningSessionStatus::InProgress,
            'started_at' => now(),
            'payment_status' => PaymentStatus::Pending,
        ]);
        $order = Order::query()->create([
            'table_id' => $table->id,
            'dining_session_id' => $session->id,
            'status' => OrderStatus::Preparing,
            'total_amount' => 30.00,
        ]);
        $category = Category::query()->create(['name' => 'Main']);
        $menu = MenuItem::query()->create([
            'name' => 'Beer',
            'price' => 10.00,
            'category_id' => $category->id,
        ]);
        $item = OrderItem::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $menu->id,
            'quantity' => 3,
            'price' => 10.00,
            'preparation_status' => PreparationStatus::Ready,
            'delivered_quantity' => 0,
            'is_delivered' => false,
        ]);

        $this->actingAs($user)
            ->postJson(route('orders.items.deliver', [$order, $item]), ['quantity' => 1])
            ->assertOk()
            ->assertJsonPath('order.items.0.delivered_quantity', 1)
            ->assertJsonPath('order.items.0.remaining_quantity', 2);

        $item->refresh();
        $this->assertSame(1, (int) $item->delivered_quantity);
        $this->assertFalse((bool) $item->is_delivered);

        $this->actingAs($user)
            ->postJson(route('orders.items.deliver', [$order, $item]), ['quantity' => 2])
            ->assertOk()
            ->assertJsonPath('order.items.0.delivered_quantity', 3)
            ->assertJsonPath('order.items.0.preparation_status', PreparationStatus::Delivered->value)
            ->assertJsonPath('order.status', OrderStatus::Completed->value);

        $session->refresh();
        $this->assertSame(DiningSessionStatus::FoodDelivered, $session->status);
    }
}
