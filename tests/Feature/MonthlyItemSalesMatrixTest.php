<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyItemSalesMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAdmin(): User
    {
        $user = User::factory()->create();
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin']);
        $user->roles()->attach($adminRole);

        return $user;
    }

    public function test_matrix_page_shows_grouped_columns_and_daily_totals(): void
    {
        $catPizza = Category::query()->create(['name' => 'Pizza Category']);
        $catDrinks = Category::query()->create(['name' => 'Drinks']);

        $pizza = MenuItem::query()->create(['name' => 'Margherita', 'price' => 1200, 'category_id' => $catPizza->id]);
        $burger = MenuItem::query()->create(['name' => 'Classic Burger', 'price' => 900, 'category_id' => $catPizza->id]);
        $coke = MenuItem::query()->create(['name' => 'Coke', 'price' => 200, 'category_id' => $catDrinks->id]);

        $table = DiningTable::query()->create(['table_number' => 1, 'status' => 'available']);

        $day = Carbon::create(2026, 5, 3, 14, 0, 0, config('app.timezone'));

        $order = Order::query()->create([
            'table_id' => $table->id,
            'status' => OrderStatus::Completed,
            'total_amount' => 50,
            'ordered_at' => $day,
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $pizza->id,
            'quantity' => 2,
            'price' => 1200,
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $coke->id,
            'quantity' => 3,
            'price' => 200,
        ]);

        Order::query()->create([
            'table_id' => $table->id,
            'status' => OrderStatus::Pending,
            'total_amount' => 999,
            'ordered_at' => $day,
        ]);

        $this->actingAs($this->actingAdmin())
            ->get(route('reporting.monthly-item-sales-matrix', [
                'year' => 2026,
                'month' => 5,
            ]))
            ->assertOk()
            ->assertSee('Pizza Category')
            ->assertSee('Drinks')
            ->assertSee('Margherita')
            ->assertSee('Classic Burger')
            ->assertSee('Coke')
            ->assertSee('Item sales matrix')
            ->assertSee('Quantity + sales (¥)')
            ->assertSee('2026-05-03')
            // May 03: pizza qty 2 / ¥2,400 ; coke qty 3 / ¥600 ; burger 0 / ¥0
            ->assertSee('2,400')
            ->assertSee('600');
    }

    public function test_csv_export_streams_utf8_payload(): void
    {
        $cat = Category::query()->create(['name' => 'Solo']);
        $item = MenuItem::query()->create(['name' => 'OneItem', 'price' => 10, 'category_id' => $cat->id]);
        $table = DiningTable::query()->create(['table_number' => 2, 'status' => 'available']);
        $order = Order::query()->create([
            'table_id' => $table->id,
            'status' => OrderStatus::CheckoutDone,
            'total_amount' => 10,
            'ordered_at' => Carbon::create(2026, 5, 1, 10, 0, 0),
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $item->id,
            'quantity' => 7,
            'price' => 10,
        ]);

        $res = $this->actingAs($this->actingAdmin())
            ->get(route('reporting.monthly-item-sales-matrix.csv', [
                'year' => 2026,
                'month' => 5,
            ]));
        $res->assertOk();
        $bin = $res->streamedContent();

        // UTF-8 BOM + headings
        $this->assertStringStartsWith("\xEF\xBB\xBF", $bin);
        $this->assertStringContainsString('TOTAL', $bin);
        $this->assertStringContainsString('Solo', $bin);
        $this->assertStringContainsString('OneItem', $bin);
        $this->assertStringContainsString('7', $bin);
        $this->assertStringContainsString('70.00', $bin);
    }

    public function test_pdf_export_returns_pdf_stream(): void
    {
        $cat = Category::query()->create(['name' => 'PDF Cat']);
        $item = MenuItem::query()->create(['name' => 'PDF Item', 'price' => 1, 'category_id' => $cat->id]);
        $table = DiningTable::query()->create(['table_number' => 3, 'status' => 'available']);
        $order = Order::query()->create([
            'table_id' => $table->id,
            'status' => OrderStatus::Completed,
            'total_amount' => 1,
            'ordered_at' => Carbon::create(2026, 6, 1, 11, 0, 0),
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $item->id,
            'quantity' => 1,
            'price' => 1,
        ]);

        $res = $this->actingAs($this->actingAdmin())
            ->get(route('reporting.monthly-item-sales-matrix.pdf', [
                'year' => 2026,
                'month' => 6,
            ]));

        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('content-type'));
    }
}
