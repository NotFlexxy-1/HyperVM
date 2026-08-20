<?php

namespace App\Services\Proxmox;

use App\Exceptions\ProxmoxRequestException;
use App\Models\Allocation;
use App\Models\Node;
use App\Models\Plan;
use App\Models\Server;
use App\Models\ServerTask;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServerService
{
    public function __construct(private readonly NodeService $nodes) {}

    /**
     * Create the database record and the matching QEMU virtual machine on Proxmox.
     *
     * $data keys: name, description, owner_id, node_id, plan_id, template, cpu_cores,
     * memory_mb, disk_mb, allocation_ids[], ssh_keys, root_password, start_after_install
     */
    public function create(array $data): Server
    {
        /** @var Node $node */
        $node = Node::findOrFail($data['node_id']);
        $plan = isset($data['plan_id']) ? Plan::find($data['plan_id']) : null;

        $cpu = (int) ($data['cpu_cores'] ?? $plan?->cpu_cores);
        $memory = (int) ($data['memory_mb'] ?? $plan?->memory_mb);
        $disk = (int) ($data['disk_mb'] ?? $plan?->disk_mb);

        if (! $node->hasCapacityFor($memory, $disk, $cpu)) {
            throw new ProxmoxRequestException("Node {$node->name} does not have enough free capacity for this server.");
        }

        $vmid = $this->nodes->nextAvailableVmid($node);

        $server = DB::transaction(function () use ($data, $node, $plan, $cpu, $memory, $disk, $vmid) {
            $server = Server::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'owner_id' => $data['owner_id'],
                'node_id' => $node->id,
                'plan_id' => $plan?->id,
                'vmid' => $vmid,
                'status' => Server::STATUS_INSTALLING,
                'cpu_cores' => $cpu,
                'memory_mb' => $memory,
                'disk_mb' => $disk,
                'bandwidth_gb' => $data['bandwidth_gb'] ?? $plan?->bandwidth_gb,
                'snapshot_limit' => $data['snapshot_limit'] ?? $plan?->snapshot_limit ?? 2,
                'backup_limit' => $data['backup_limit'] ?? $plan?->backup_limit ?? 2,
                'network_speed_mbps' => $data['network_speed_mbps'] ?? $plan?->network_speed_mbps,
                'template' => $data['template'] ?? null,
                'os_type' => $data['os_type'] ?? config('hypervm.proxmox.default_os_type'),
            ]);

            Allocation::whereIn('id', $data['allocation_ids'] ?? [])
                ->where('node_id', $node->id)
                ->whereNull('server_id')
                ->update(['server_id' => $server->id]);

            return $server;
        });

        try {
            $upid = $this->buildVirtualMachine($server, $data);
            $this->recordTask($server, 'server.create', $upid, $data['actor_id'] ?? null);

            $server->forceFill([
                'status' => Server::STATUS_READY,
                'installed_at' => now(),
            ])->save();
        } catch (ProxmoxRequestException $e) {
            $server->forceFill(['status' => Server::STATUS_INSTALL_FAILED])->save();

            throw $e;
        }

        return $server->refresh();
    }

    /** Issue the actual qemu create call and configure cloud-init networking. */
    private function buildVirtualMachine(Server $server, array $data): ?string
    {
        $node = $server->node;
        $allocation = $server->allocations()->first();

        $payload = [
            'vmid' => $server->vmid,
            'name' => Str::slug($server->name).'-'.$server->uuid_short,
            'cores' => $server->cpu_cores,
            'sockets' => 1,
            'memory' => $server->memory_mb,
            'ostype' => $server->os_type,
            'agent' => 'enabled=1',
            'scsihw' => 'virtio-scsi-single',
            'boot' => 'order=scsi0',
            'onboot' => 1,
            'scsi0' => sprintf('%s:%d,discard=on,ssd=1', $node->storage_pool, max(1, (int) round($server->disk_mb / 1024))),
            'ide2' => sprintf('%s:cloudinit', $node->storage_pool),
            'net0' => $this->buildNetworkString($server, $allocation),
            'description' => sprintf("Managed by HyperVM\nServer UUID: %s\nOwner: %s", $server->uuid, $server->owner->email),
        ];

        if ($allocation) {
            $payload['ipconfig0'] = sprintf(
                'ip=%s/%d%s',
                $allocation->address,
                $allocation->cidr,
                $allocation->gateway ? ",gw={$allocation->gateway}" : '',
            );
        }

        if (! empty($data['ssh_keys'])) {
            $payload['sshkeys'] = rawurlencode($data['ssh_keys']);
        }

        if (! empty($data['root_password'])) {
            $payload['cipassword'] = $data['root_password'];
            $payload['ciuser'] = $data['ci_user'] ?? 'root';
        }

        if (! empty($data['template'])) {
            // Templates are Proxmox VM templates named after the key in
            // config('hypervm.proxmox.supported_templates'); clone instead of create.
            $templateVmid = $this->resolveTemplateVmid($node, $data['template']);

            if ($templateVmid !== null) {
                $upid = $node->client()->post("nodes/{$node->proxmox_node_name}/qemu/{$templateVmid}/clone", [
                    'newid' => $server->vmid,
                    'name' => $payload['name'],
                    'full' => 1,
                    'storage' => $node->storage_pool,
                ]);

                $this->waitForTask($node, $upid);
                $this->updateConfiguration($server, array_intersect_key($payload, array_flip([
                    'cores', 'memory', 'net0', 'ipconfig0', 'sshkeys', 'cipassword', 'ciuser', 'description', 'onboot',
                ])));

                if (! empty($data['start_after_install'])) {
                    $this->power($server, 'start');
                }

                return is_string($upid) ? $upid : null;
            }
        }

        $upid = $node->client()->post("nodes/{$node->proxmox_node_name}/qemu", $payload);
        $this->waitForTask($node, $upid);

        if (! empty($data['start_after_install'])) {
            $this->power($server, 'start');
        }

        return is_string($upid) ? $upid : null;
    }

    private function resolveTemplateVmid(Node $node, string $template): ?int
    {
        foreach ($this->nodes->virtualMachines($node) as $vm) {
            if ((int) ($vm['template'] ?? 0) === 1 && str_contains((string) ($vm['name'] ?? ''), $template)) {
                return (int) $vm['vmid'];
            }
        }

        return null;
    }

    private function buildNetworkString(Server $server, ?Allocation $allocation): string
    {
        $parts = ['virtio'];

        if ($allocation?->mac_address) {
            $parts[0] = 'virtio='.strtoupper($allocation->mac_address);
        }

        $parts[] = 'bridge='.$server->node->network_bridge;

        if ($allocation?->vlan_id) {
            $parts[] = 'tag='.$allocation->vlan_id;
        }

        if ($server->network_speed_mbps) {
            $parts[] = 'rate='.round($server->network_speed_mbps / 8, 2);
        }

        return implode(',', $parts);
    }

    public function updateConfiguration(Server $server, array $payload): void
    {
        $node = $server->node;
        $node->client()->post("nodes/{$node->proxmox_node_name}/qemu/{$server->vmid}/config", $payload);
    }

    /** @param 'start'|'stop'|'shutdown'|'reboot'|'reset'|'suspend'|'resume' $action */
    public function power(Server $server, string $action): ?string
    {
        $allowed = ['start', 'stop', 'shutdown', 'reboot', 'reset', 'suspend', 'resume'];

        if (! in_array($action, $allowed, true)) {
            throw new ProxmoxRequestException("Unsupported power action [{$action}].");
        }

        $node = $server->node;
        $upid = $node->client()->post("nodes/{$node->proxmox_node_name}/qemu/{$server->vmid}/status/{$action}");

        Cache::forget($this->statusCacheKey($server));

        return is_string($upid) ? $upid : null;
    }

    public function status(Server $server): array
    {
        return Cache::remember(
            $this->statusCacheKey($server),
            (int) config('hypervm.proxmox.metrics_cache_seconds', 10),
            function () use ($server) {
                $node = $server->node;

                return (array) $node->client()->get("nodes/{$node->proxmox_node_name}/qemu/{$server->vmid}/status/current");
            },
        );
    }

    /** RRD time-series for graphs: hour, day, week, month or year. */
    public function metrics(Server $server, string $timeframe = 'hour'): array
    {
        $node = $server->node;

        return (array) ($node->client()->get("nodes/{$node->proxmox_node_name}/qemu/{$server->vmid}/rrddata", [
            'timeframe' => $timeframe,
            'cf' => 'AVERAGE',
        ]) ?? []);
    }

    /** Issue a one-time noVNC ticket used by the browser console. */
    public function consoleTicket(Server $server): array
    {
        $node = $server->node;

        $ticket = (array) $node->client()->post("nodes/{$node->proxmox_node_name}/qemu/{$server->vmid}/vncproxy", [
            'websocket' => 1,
            'generate-password' => 0,
        ]);

        return [
            'ticket' => $ticket['ticket'] ?? null,
            'port' => $ticket['port'] ?? null,
            'user' => $ticket['user'] ?? null,
            'cert' => $ticket['cert'] ?? null,
            'node' => $node->proxmox_node_name,
            'host' => $node->fqdn,
            'api_port' => $node->port,
            'vmid' => $server->vmid,
        ];
    }

    public function resize(Server $server, int $additionalGb, string $disk = 'scsi0'): void
    {
        $node = $server->node;
        $node->client()->put("nodes/{$node->proxmox_node_name}/qemu/{$server->vmid}/resize", [
            'disk' => $disk,
            'size' => "+{$additionalGb}G",
        ]);

        $server->increment('disk_mb', $additionalGb * 1024);
    }

    public function suspend(Server $server): void
    {
        try {
            $this->power($server, 'stop');
        } catch (ProxmoxRequestException) {
            // The VM may already be stopped; suspension must still be recorded.
        }

        $server->forceFill(['status' => Server::STATUS_SUSPENDED, 'suspended_at' => now()])->save();
    }

    public function unsuspend(Server $server): void
    {
        $server->forceFill(['status' => Server::STATUS_READY, 'suspended_at' => null])->save();
    }

    public function destroy(Server $server, bool $purgeFromProxmox = true): void
    {
        $node = $server->node;
        $server->forceFill(['status' => Server::STATUS_DELETING])->save();

        if ($purgeFromProxmox) {
            try {
                $this->power($server, 'stop');
            } catch (ProxmoxRequestException) {
                // Already stopped.
            }

            $node->client()->delete("nodes/{$node->proxmox_node_name}/qemu/{$server->vmid}", [
                'purge' => 1,
                'destroy-unreferenced-disks' => 1,
            ]);
        }

        DB::transaction(function () use ($server) {
            $server->allocations()->update(['server_id' => null]);
            $server->delete();
        });
    }

    public function rebuild(Server $server, string $template, ?string $rootPassword = null, ?string $sshKeys = null): void
    {
        $this->destroy($server, purgeFromProxmox: true);

        throw new ProxmoxRequestException(
            'Rebuild removes the current disk; recreate the server from the template using ServerService::create().'
        );
    }

    public function waitForTask(Node $node, mixed $upid, int $maxSeconds = 300): array
    {
        if (! is_string($upid) || $upid === '') {
            return [];
        }

        $deadline = time() + $maxSeconds;

        do {
            $status = (array) $node->client()->get("nodes/{$node->proxmox_node_name}/tasks/".rawurlencode($upid).'/status');

            if (($status['status'] ?? '') !== 'running') {
                if (($status['exitstatus'] ?? 'OK') !== 'OK') {
                    throw new ProxmoxRequestException("Proxmox task failed: {$status['exitstatus']}", null, $upid);
                }

                return $status;
            }

            usleep(750_000);
        } while (time() < $deadline);

        throw new ProxmoxRequestException("Timed out waiting for Proxmox task {$upid}.");
    }

    public function recordTask(Server $server, string $action, ?string $upid, ?int $userId = null, array $payload = []): ServerTask
    {
        return $server->tasks()->create([
            'user_id' => $userId,
            'action' => $action,
            'upid' => $upid,
            'status' => 'completed',
            'payload' => $payload ?: null,
            'finished_at' => now(),
        ]);
    }

    private function statusCacheKey(Server $server): string
    {
        return "hypervm:server:{$server->id}:status";
    }

    public function transferOwnership(Server $server, User $newOwner): void
    {
        $server->forceFill(['owner_id' => $newOwner->id])->save();
    }
}
