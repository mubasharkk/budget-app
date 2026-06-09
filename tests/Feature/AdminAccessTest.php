<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_admin_panel(): void
    {
        $this->get('/admin/receipt')
            ->assertRedirect('/admin/login');
    }

    public function test_authenticated_non_admin_is_blocked_from_admin_panel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'backpack')
            ->get('/admin/receipt')
            ->assertRedirect('/admin/login');
    }

    public function test_admin_user_can_access_admin_panel(): void
    {
        Role::firstOrCreate(['name' => 'admin']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin, 'backpack')
            ->get('/admin/receipt')
            ->assertOk();
    }
}
