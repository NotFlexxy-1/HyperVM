<?php

namespace App\Support;

/**
 * Canonical permission list. Seeded by Database\Seeders\PermissionSeeder and
 * validated by the role editor.
 */
class Permissions
{
    /** Roles created by Database\Seeders\PermissionSeeder. */
    public const ROLE_ADMIN = 'admin';

    public const ROLE_MODERATOR = 'moderator';

    public const ROLE_USER = 'user';

    public const GROUPS = [

        'Servers' => [
            'server.view.all' => 'View every server on the panel',
            'server.create' => 'Provision new servers',
            'server.update' => 'Change server resources and settings',
            'server.delete' => 'Delete servers',
            'server.suspend' => 'Suspend and unsuspend servers',
            'server.power' => 'Send power actions',
            'server.console' => 'Open the VNC console',
            'server.backup' => 'Create and restore backups',
            'server.snapshot' => 'Create and roll back snapshots',
            'server.reinstall' => 'Reinstall the operating system',
        ],
        'Nodes' => [
            'node.view' => 'View nodes',
            'node.create' => 'Add nodes',
            'node.update' => 'Edit nodes',
            'node.delete' => 'Delete nodes',
            'node.allocations' => 'Manage IP allocations',
        ],
        'Users' => [
            'user.view' => 'View users',
            'user.create' => 'Create users',
            'user.update' => 'Edit users',
            'user.delete' => 'Delete users',
            'user.password' => 'Reset user passwords',
        ],
        'Platform' => [
            'plan.manage' => 'Manage plans',
            'location.manage' => 'Manage locations',
            'role.manage' => 'Manage roles and permissions',
            'settings.manage' => 'Change panel settings, branding and layout',
            'audit.view' => 'Read the audit log',
            'api.manage' => 'Manage API keys',
        ],
    ];

    /** @return array<int,string> */
    public static function all(): array
    {
        return array_merge(...array_map('array_keys', array_values(self::GROUPS)));
    }

    public static function grouped(): array
    {
        return self::GROUPS;
    }
}
