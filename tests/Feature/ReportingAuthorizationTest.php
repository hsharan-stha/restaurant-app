<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportingAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_cannot_access_reporting_routes(): void
    {
        $user = User::factory()->create();
        $staffRole = Role::query()->create(['name' => 'staff']);
        $user->roles()->attach($staffRole);

        $urls = [
            route('reporting.completed-orders'),
            route('reporting.monthly-item-sales-matrix'),
            route('reporting.monthly-item-sales-matrix.csv'),
            route('reporting.monthly-item-sales-matrix.pdf'),
        ];

        foreach ($urls as $url) {
            $this->actingAs($user)->get($url)->assertForbidden();
        }
    }

    public function test_admin_can_access_reporting_index_routes(): void
    {
        $user = User::factory()->create();
        $adminRole = Role::query()->create(['name' => 'admin']);
        $user->roles()->attach($adminRole);

        $this->actingAs($user)
            ->get(route('reporting.completed-orders'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('reporting.monthly-item-sales-matrix'))
            ->assertOk();
    }
}
