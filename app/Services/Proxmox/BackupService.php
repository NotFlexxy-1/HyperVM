<?php

namespace App\Services\Proxmox;

use App\Exceptions\ProxmoxRequestException;
use App\Models\Server;
use App\Models\ServerBackup;
use App\Models\ServerSnapshot;

class BackupService
{
    public function __construct(private readonly ServerService $servers) {}

    public function createBackup(Server $server, ?string $name = null, string $compression = 'zstd'): ServerBackup
    {
        if ($server->backups()->count() >= $server->backup_limit) {
            throw new ProxmoxRequestException('This server has reached its backup limit.');
        }

        $node = $server->node;
        $storage = $node->backup_storage_pool ?: $node->storage_pool;

        $backup = $server->backups()->create([
            'name' => $name ?: 'Backup '.now()->toDateTimeString(),
            'compression_type' => $compression,
        ]);

        $upid = $node->client()->post("nodes/{$node->proxmox_node_name}/vzdump", [
            'vmid' => $server->vmid,
            'storage' => $storage,
            'compress' => $compression,
            'mode' => 'snapshot',
            'remove' => 0,
        ]);

        $this->servers->waitForTask($node, $upid, 3600);

        $volume = collect((array) $node->client()->get("nodes/{$node->proxmox_node_name}/storage/{$storage}/content", [
            'content' => 'backup',
            'vmid' => $server->vmid,
        ]))->sortByDesc('ctime')->first();

        $backup->forceFill([
            'volume_id' => $volume['volid'] ?? null,
            'size_bytes' => isset($volume['size']) ? (int) $volume['size'] : null,
            'is_successful' => true,
            'completed_at' => now(),
        ])->save();

        return $backup;
    }

    public function restoreBackup(Server $server, ServerBackup $backup): void
    {
        if (! $backup->volume_id) {
            throw new ProxmoxRequestException('This backup has no associated Proxmox volume.');
        }

        $node = $server->node;
        $server->forceFill(['status' => Server::STATUS_RESTORING, 'is_locked' => true])->save();

        try {
            $upid = $node->client()->post("nodes/{$node->proxmox_node_name}/qemu", [
                'vmid' => $server->vmid,
                'archive' => $backup->volume_id,
                'force' => 1,
                'storage' => $node->storage_pool,
            ]);

            $this->servers->waitForTask($node, $upid, 3600);
        } finally {
            $server->forceFill(['status' => Server::STATUS_READY, 'is_locked' => false])->save();
        }
    }

    public function deleteBackup(ServerBackup $backup): void
    {
        $node = $backup->server->node;

        if ($backup->volume_id) {
            $storage = $node->backup_storage_pool ?: $node->storage_pool;
            $node->client()->delete("nodes/{$node->proxmox_node_name}/storage/{$storage}/content/".rawurlencode($backup->volume_id));
        }

        $backup->delete();
    }

    public function createSnapshot(Server $server, string $name, ?string $description = null, bool $includeRam = false): ServerSnapshot
    {
        if ($server->snapshots()->count() >= $server->snapshot_limit) {
            throw new ProxmoxRequestException('This server has reached its snapshot limit.');
        }

        $node = $server->node;

        $snapshot = $server->snapshots()->create([
            'name' => $name,
            'description' => $description,
            'include_ram' => $includeRam,
        ]);

        $upid = $node->client()->post("nodes/{$node->proxmox_node_name}/qemu/{$server->vmid}/snapshot", [
            'snapname' => $name,
            'description' => (string) $description,
            'vmstate' => $includeRam ? 1 : 0,
        ]);

        $this->servers->waitForTask($node, $upid, 1800);
        $snapshot->forceFill(['is_successful' => true])->save();

        return $snapshot;
    }

    public function rollbackSnapshot(Server $server, ServerSnapshot $snapshot): void
    {
        $node = $server->node;
        $upid = $node->client()->post("nodes/{$node->proxmox_node_name}/qemu/{$server->vmid}/snapshot/{$snapshot->name}/rollback");
        $this->servers->waitForTask($node, $upid, 1800);
    }

    public function deleteSnapshot(Server $server, ServerSnapshot $snapshot): void
    {
        $node = $server->node;
        $upid = $node->client()->delete("nodes/{$node->proxmox_node_name}/qemu/{$server->vmid}/snapshot/{$snapshot->name}");
        $this->servers->waitForTask($node, $upid, 1800);
        $snapshot->delete();
    }
}
