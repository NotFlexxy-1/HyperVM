<?php

namespace Database\Seeders;

use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Permissions::all() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $admin = Role::findOrCreate('admin', 'web');
        $admin->forceFill(['description' => 'Full access to every panel feature.', 'colour' => '#5b8cff', 'is_protected' => true])->save();
        $admin->syncPermissions(Permissions::all());

        $moderator = Role::findOrCreate('moderator', 'web');
        $moderator->forceFill(['description' => 'Day-to-day support: servers, users, nodes (read only).', 'colour' => '#22d3ee'])->save();
        $moderator->syncPermissions([
            'server.view.all', 'server.power', 'server.console', 'server.suspend', 'server.update',
            'node.view', 'user.view', 'user.update', 'audit.view',
        ]);

        $user = Role::findOrCreate('user', 'web');
        $user->forceFill(['description' => 'Standard client account.', 'colour' => '#94a3b8', 'is_protected' => true])->save();
        $user->syncPermissions([]);

        Artisan::call('permission:cache-reset');
    }
}
