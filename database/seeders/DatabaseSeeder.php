<?php

namespace Database\Seeders;

use App\Enums\TableStatus;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin']);
        $staffRole = Role::query()->firstOrCreate(['name' => 'staff']);

        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@restaurant.test',
            'password' => bcrypt('password'),
        ]);
        $admin->roles()->sync([$adminRole->id]);

        $staff = User::factory()->create([
            'name' => 'Staff User',
            'email' => 'staff@restaurant.test',
            'password' => bcrypt('password'),
        ]);
        $staff->roles()->sync([$staffRole->id]);

        for ($i = 1; $i <= 5; $i++) {
            DiningTable::query()->create([
                'table_number' => $i,
                'status' => TableStatus::Available,
            ]);
        }

        $categoryNames = ['Appetizers', 'Main courses', 'Drinks', 'Desserts', 'Sides'];
        $categories = collect($categoryNames)->map(fn ($name) => Category::query()->create(['name' => $name]));

        $items = [
            ['Spring rolls', 6.50], ['Edamame', 5.00], ['Gyoza', 7.00], ['Miso soup', 4.50],
            ['Teriyaki bowl', 14.00], ['Ramen', 13.50], ['Curry rice', 12.00], ['Grilled salmon', 18.00],
            ['Green tea', 3.00], ['Soda', 2.50], ['Craft beer', 6.00], ['House wine', 7.50],
            ['Mochi ice cream', 6.00], ['Cheesecake', 7.00], ['Brownie', 5.50], ['Matcha tiramisu', 8.00],
            ['Steamed rice', 3.00], ['Pickles', 2.50], ['House salad', 5.00], ['Kimchi', 3.50],
        ];

        foreach ($items as $index => $data) {
            $category = $categories[$index % $categories->count()];
            MenuItem::query()->create([
                'name' => $data[0],
                'price' => $data[1],
                'category_id' => $category->id,
                'image' => null,
            ]);
        }
    }
}
