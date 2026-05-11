<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Category;
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

class ThermalBillTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $user = User::factory()->create();
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin']);
        $user->roles()->attach($adminRole);

        return $user;
    }

    public function test_admin_can_open_thermal_bill_screen_with_print_actions(): void
    {
        $table = DiningTable::query()->create(['table_number' => 10, 'status' => 'available']);
        $category = Category::query()->create(['name' => 'Rice', 'sort_order' => 0, 'is_active' => true]);
        $menuItem = MenuItem::query()->create([
            'name' => 'Fried rice',
            'price' => 900,
            'category_id' => $category->id,
            'is_available' => true,
        ]);
        $order = Order::query()->create([
            'table_id' => $table->id,
            'status' => OrderStatus::CheckoutDone,
            'total_amount' => 900,
            'checkout_at' => now(),
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
            'quantity' => 2,
            'price' => 900,
        ]);
        Invoice::query()->create([
            'order_id' => $order->id,
            'subtotal' => 1800,
            'tax' => 180,
            'total' => 1980,
        ]);
        Payment::query()->create([
            'order_id' => $order->id,
            'method' => PaymentMethod::Cash,
            'status' => PaymentStatus::Completed,
        ]);

        $this->actingAs($this->admin())
            ->get(route('bills.thermal', ['order' => $order, 'autoprint' => 1]))
            ->assertOk()
            ->assertSee('Print Bill')
            ->assertSee('THERMAL CUSTOMER BILL')
            ->assertSee('Fried rice')
            ->assertSee('Grand Total')
            ->assertSee('window.print()');
    }

    public function test_admin_can_download_thermal_bill_pdf(): void
    {
        $table = DiningTable::query()->create(['table_number' => 3, 'status' => 'available']);
        $category = Category::query()->create(['name' => 'Noodle', 'sort_order' => 0, 'is_active' => true]);
        $menuItem = MenuItem::query()->create([
            'name' => 'Udon',
            'price' => 700,
            'category_id' => $category->id,
            'is_available' => true,
        ]);
        $order = Order::query()->create([
            'table_id' => $table->id,
            'status' => OrderStatus::CheckoutDone,
            'total_amount' => 700,
            'checkout_at' => now(),
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
            'quantity' => 1,
            'price' => 700,
        ]);
        Invoice::query()->create([
            'order_id' => $order->id,
            'subtotal' => 700,
            'tax' => 70,
            'total' => 770,
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('bills.thermal.pdf', ['order' => $order]));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }
}
