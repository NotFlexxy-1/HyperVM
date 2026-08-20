<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Boots every page of the panel that does not require a live Proxmox node and
 * asserts it renders without a server error.
 */
class PanelSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(SettingsSeeder::class);

        $user = User::factory()->create();
        $user->assignRole(Role::findByName(Permissions::ROLE_ADMIN));

        return $user;
    }

    public function test_guest_pages_render(): void
    {
        foreach (['/login', '/forgot-password'] as $uri) {
            $this->get($uri)->assertOk();
        }
    }

    public function test_guests_are_redirected_from_the_client_area(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/account')->assertRedirect('/login');
    }

    public function test_client_pages_render(): void
    {
        $user = $this->admin();

        foreach (['/dashboard', '/account'] as $uri) {
            $this->actingAs($user)->get($uri)->assertOk();
        }
    }

    public function test_admin_pages_render(): void
    {
        $user = $this->admin();

        $pages = [
            '/admin',
            '/admin/nodes',
            '/admin/nodes/create',
            '/admin/locations',
            '/admin/plans',
            '/admin/servers',
            '/admin/servers/create',
            '/admin/users',
            '/admin/roles',
            '/admin/settings',
            '/admin/audit-logs',
        ];

        foreach ($pages as $uri) {
            $this->actingAs($user)->get($uri)->assertOk();
        }
    }

    public function test_non_admins_cannot_reach_the_admin_area(): void
    {
        $this->seed(PermissionSeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }
}
