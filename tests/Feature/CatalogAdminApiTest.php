<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogAdminApiTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $user = User::factory()->create();
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin']);
        $user->roles()->attach($adminRole);

        return $user;
    }

    public function test_admin_can_toggle_category_active_via_ajax(): void
    {
        $cat = Category::query()->create(['name' => 'Bar', 'is_active' => true, 'sort_order' => 0]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.catalog.categories.toggle-active', $cat))
            ->assertOk()
            ->assertJsonPath('category.is_active', false);

        $this->assertFalse($cat->fresh()->is_active);
    }

    public function test_admin_inline_updates_menu_item_price(): void
    {
        $table = DiningTable::query()->create(['table_number' => 1, 'status' => 'available']);
        $cat = Category::query()->create(['name' => 'Food', 'sort_order' => 0, 'is_active' => true]);
        $item = MenuItem::query()->create([
            'name' => 'Ramen',
            'price' => 12.50,
            'category_id' => $cat->id,
            'is_available' => true,
        ]);

        $this->actingAs($this->admin())
            ->patchJson(route('admin.catalog.menu-items.inline-update', $item), ['price' => 15])
            ->assertOk();

        $this->assertSame('15.00', $item->fresh()->price);
    }

    public function test_admin_bulk_moves_items_to_another_category(): void
    {
        $catA = Category::query()->create(['name' => 'A', 'sort_order' => 0, 'is_active' => true]);
        $catB = Category::query()->create(['name' => 'B', 'sort_order' => 1, 'is_active' => true]);
        $i1 = MenuItem::query()->create(['name' => 'X', 'price' => 5, 'category_id' => $catA->id]);
        $i2 = MenuItem::query()->create(['name' => 'Y', 'price' => 6, 'category_id' => $catA->id]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.catalog.menu-items.bulk'), [
                'action' => 'set_category',
                'ids' => [$i1->id, $i2->id],
                'category_id' => $catB->id,
            ])
            ->assertOk();

        $this->assertSame($catB->id, $i1->fresh()->category_id);
        $this->assertSame($catB->id, $i2->fresh()->category_id);
    }

    public function test_staff_cannot_access_catalog_ajax_routes(): void
    {
        $staffRole = Role::query()->firstOrCreate(['name' => 'staff']);
        $staff = User::factory()->create();
        $staff->roles()->attach($staffRole);

        $cat = Category::query()->create(['name' => 'X', 'sort_order' => 0, 'is_active' => true]);

        $this->actingAs($staff)
            ->getJson(route('admin.catalog.categories.index'))
            ->assertForbidden();
    }
}
