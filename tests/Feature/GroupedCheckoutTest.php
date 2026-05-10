<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
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

class GroupedCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_page_groups_completed_orders_for_same_customer_session(): void
    {
        [$user, $firstOrder, $secondOrder] = $this->seedGroupedCheckoutData();

        $this->actingAs($user)
            ->get(route('payments.create', $firstOrder))
            ->assertOk()
            ->assertSee('Session checkout')
            ->assertSee('Order #'.$firstOrder->id)
            ->assertSee('Order #'.$secondOrder->id)
            ->assertSeeHtml('&yen;32.40');
    }

    public function test_grouped_checkout_marks_all_completed_session_orders_as_paid(): void
    {
        [$user, $firstOrder, $secondOrder, $table] = $this->seedGroupedCheckoutData();

        $this->actingAs($user)
            ->post(route('payments.store', $firstOrder), [
                'method' => 'cash',
            ])
            ->assertRedirect(route('orders.show', $firstOrder));

        $this->assertSame(2, Payment::query()->where('status', PaymentStatus::Completed)->count());
        $this->assertTrue($firstOrder->fresh()->payments()->where('status', PaymentStatus::Completed)->exists());
        $this->assertTrue($secondOrder->fresh()->payments()->where('status', PaymentStatus::Completed)->exists());
        $this->assertSame('available', $table->fresh()->status->value);
    }

    /**
     * @return array{0: User, 1: Order, 2: Order, 3: DiningTable}
     */
    protected function seedGroupedCheckoutData(): array
    {
        $user = User::factory()->create();
        $staffRole = Role::query()->create(['name' => 'staff']);
        $user->roles()->attach($staffRole);

        $table = DiningTable::query()->create([
            'table_number' => 4,
            'status' => 'occupied',
        ]);

        $session = CustomerSession::query()->create([
            'table_id' => $table->id,
            'session_token' => 'grouped-session',
            'started_at' => now(),
            'last_seen_at' => now(),
        ]);

        $category = Category::query()->create(['name' => 'Dinner']);
        $item = MenuItem::query()->create([
            'name' => 'Set Meal',
            'price' => 10.00,
            'category_id' => $category->id,
        ]);

        $firstOrder = Order::query()->create([
            'table_id' => $table->id,
            'customer_session_id' => $session->id,
            'status' => OrderStatus::Completed,
            'total_amount' => 10.00,
        ]);
        OrderItem::query()->create([
            'order_id' => $firstOrder->id,
            'menu_item_id' => $item->id,
            'quantity' => 1,
            'price' => 10.00,
        ]);
        Invoice::query()->create([
            'order_id' => $firstOrder->id,
            'subtotal' => 10.00,
            'tax' => 0.80,
            'total' => 10.80,
        ]);

        $secondOrder = Order::query()->create([
            'table_id' => $table->id,
            'customer_session_id' => $session->id,
            'status' => OrderStatus::Completed,
            'total_amount' => 20.00,
        ]);
        OrderItem::query()->create([
            'order_id' => $secondOrder->id,
            'menu_item_id' => $item->id,
            'quantity' => 2,
            'price' => 10.00,
        ]);
        Invoice::query()->create([
            'order_id' => $secondOrder->id,
            'subtotal' => 20.00,
            'tax' => 1.60,
            'total' => 21.60,
        ]);

        return [$user, $firstOrder, $secondOrder, $table];
    }
}
