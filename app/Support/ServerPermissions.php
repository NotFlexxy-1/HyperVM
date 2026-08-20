<?php

namespace App\Support;

use App\Models\Server;
use App\Models\User;

/**
 * Per-server (sub-user) permissions. The owner implicitly holds every
 * permission; sub-users hold exactly what is stored on the server_user pivot;
 * staff holding the global `server.view.all` permission get read access and
 * anything their global permissions allow.
 */
class ServerPermissions
{
    public const GROUPS = [
        'Control' => [
            'control.console' => 'Open the VNC console',
            'control.start' => 'Start the server',
            'control.stop' => 'Stop / shutdown the server',
            'control.restart' => 'Reboot the server',
        ],
        'Management' => [
            'settings.rename' => 'Rename the server',
            'settings.reinstall' => 'Reinstall the operating system',
            'settings.hardware' => 'Change CPU, memory and disk',
            'settings.cloudinit' => 'Change cloud-init credentials',
        ],
        'Data' => [
            'backup.read' => 'View backups',
            'backup.create' => 'Create backups',
            'backup.restore' => 'Restore backups',
            'backup.delete' => 'Delete backups',
            'snapshot.read' => 'View snapshots',
            'snapshot.create' => 'Create snapshots',
            'snapshot.rollback' => 'Roll back snapshots',
            'snapshot.delete' => 'Delete snapshots',
        ],
        'Networking' => [
            'network.read' => 'View network configuration',
            'network.update' => 'Change network configuration and firewall',
            'media.manage' => 'Mount and unmount ISO media',
        ],
        'Team' => [
            'activity.read' => 'View the activity log',
            'subuser.read' => 'View sub-users',
            'subuser.manage' => 'Invite and remove sub-users',
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

    public static function isOwner(User $user, Server $server): bool
    {
        return $server->owner_id === $user->id;
    }

    /** @return array<int,string> */
    public static function for(User $user, Server $server): array
    {
        if (self::isOwner($user, $server) || $user->can('server.view.all')) {
            return self::all();
        }

        $pivot = $server->subusers()->whereKey($user->id)->first()?->pivot;

        return array_values((array) ($pivot?->permissions ? json_decode($pivot->permissions, true) : []));
    }

    public static function allows(User $user, Server $server, string $permission): bool
    {
        return in_array($permission, self::for($user, $server), true);
    }
}
