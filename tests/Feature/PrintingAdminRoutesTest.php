<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrintingAdminRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $user = User::factory()->create();
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin']);
        $user->roles()->attach($adminRole);

        return $user;
    }

    protected function staff(): User
    {
        $user = User::factory()->create();
        $staffRole = Role::query()->firstOrCreate(['name' => 'staff']);
        $user->roles()->attach($staffRole);

        return $user;
    }

    public function test_admin_can_open_printing_pages(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.printing.printers.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.printing.settings.edit'))->assertOk();
        $this->actingAs($admin)->get(route('admin.printing.logs.index'))->assertOk();
    }

    public function test_staff_cannot_open_printing_pages(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)->get(route('admin.printing.printers.index'))->assertForbidden();
    }
}
