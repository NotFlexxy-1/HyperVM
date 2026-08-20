<?php

namespace App\Services\Proxmox;

use App\Exceptions\ProxmoxRequestException;
use App\Models\Server;

/**
 * Everything that reads or mutates the *configuration* of an existing QEMU
 * virtual machine: hardware, cloud-init, networking, removable media and the
 * per-VM firewall. Every method talks to the real Proxmox VE API — there are
 * no cached or synthesised responses here.
 */
class ServerConfigService
{
    public function __construct(private readonly ServerService $servers) {}

    private function base(Server $server): string
    {
        return "nodes/{$server->node->proxmox_node_name}/qemu/{$server->vmid}";
    }

    /** Current qemu config (cores, memory, net0, ipconfig0, scsi0, boot, ...). */
    public function config(Server $server): array
    {
        return (array) ($server->node->client()->get($this->base($server).'/config') ?? []);
    }

    /** Pending (not yet applied) configuration entries. */
    public function pending(Server $server): array
    {
        return (array) ($server->node->client()->get($this->base($server).'/pending') ?? []);
    }

    public function update(Server $server, array $payload): void
    {
        if ($payload === []) {
            return;
        }

        $server->node->client()->post($this->base($server).'/config', $payload);
    }

    /**
     * Apply hardware limits. Only grows disks — Proxmox cannot shrink a disk
     * online, so a smaller value is rejected instead of silently ignored.
     */
    public function applyHardware(Server $server, int $cpuCores, int $memoryMb, int $diskMb, ?string $cpuLimit = null): void
    {
        if ($diskMb < $server->disk_mb) {
            throw new ProxmoxRequestException('Disks can only be grown; Proxmox does not support shrinking a virtual disk.');
        }

        $payload = [
            'cores' => $cpuCores,
            'sockets' => 1,
            'memory' => $memoryMb,
        ];

        if ($cpuLimit !== null && $cpuLimit !== '') {
            $payload['cpulimit'] = $cpuLimit;
        }

        $this->update($server, $payload);

        $growGb = (int) round(($diskMb - $server->disk_mb) / 1024);

        if ($growGb > 0) {
            $server->node->client()->put($this->base($server).'/resize', [
                'disk' => $this->primaryDisk($server),
                'size' => "+{$growGb}G",
            ]);
        }

        $server->forceFill([
            'cpu_cores' => $cpuCores,
            'memory_mb' => $memoryMb,
            'disk_mb' => $diskMb,
        ])->save();
    }

    public function primaryDisk(Server $server): string
    {
        $config = $this->config($server);

        foreach (['scsi0', 'virtio0', 'sata0', 'ide0'] as $slot) {
            if (isset($config[$slot])) {
                return $slot;
            }
        }

        return 'scsi0';
    }

    /**
     * Cloud-init user configuration. Passwords are written straight to the VM
     * config (Proxmox stores them hashed inside the cloud-init drive).
     */
    public function updateCloudInit(Server $server, array $data): void
    {
        $payload = [];

        if (array_key_exists('ci_user', $data) && $data['ci_user'] !== null) {
            $payload['ciuser'] = $data['ci_user'];
        }

        if (! empty($data['root_password'])) {
            $payload['cipassword'] = $data['root_password'];
        }

        if (array_key_exists('ssh_keys', $data) && $data['ssh_keys'] !== null) {
            $payload['sshkeys'] = rawurlencode((string) $data['ssh_keys']);
        }

        if (array_key_exists('nameserver', $data) && $data['nameserver'] !== null) {
            $payload['nameserver'] = $data['nameserver'];
        }

        if (array_key_exists('searchdomain', $data) && $data['searchdomain'] !== null) {
            $payload['searchdomain'] = $data['searchdomain'];
        }

        $this->update($server, $payload);

        if ($payload !== []) {
            // Regenerate the cloud-init drive so the changes are picked up on boot.
            $server->node->client()->put($this->base($server).'/cloudinit');
        }
    }

    /** Rebuild ipconfigN from the allocations attached to this server. */
    public function syncNetworkFromAllocations(Server $server): void
    {
        $payload = [];
        $index = 0;

        foreach ($server->allocations()->orderBy('id')->get() as $allocation) {
            $parts = ["ip={$allocation->address}/{$allocation->cidr}"];

            if ($allocation->gateway) {
                $parts[] = "gw={$allocation->gateway}";
            }

            $payload['ipconfig'.$index] = implode(',', $parts);
            $index++;
        }

        $this->update($server, $payload);
    }

    public function setNetworkRate(Server $server, ?int $speedMbps): void
    {
        $config = $this->config($server);
        $net0 = (string) ($config['net0'] ?? '');

        $parts = array_values(array_filter(
            explode(',', $net0),
            fn ($part) => $part !== '' && ! str_starts_with($part, 'rate='),
        ));

        if ($speedMbps) {
            $parts[] = 'rate='.round($speedMbps / 8, 2);
        }

        $this->update($server, ['net0' => implode(',', $parts)]);
        $server->forceFill(['network_speed_mbps' => $speedMbps])->save();
    }

    /** Guest-agent reported interfaces (requires qemu-guest-agent in the VM). */
    public function guestNetworkInterfaces(Server $server): array
    {
        $data = (array) ($server->node->client()->get($this->base($server).'/agent/network-get-interfaces') ?? []);

        return (array) ($data['result'] ?? []);
    }

    /* ------------------------------------------------------------------ */
    /* Removable media (ISO)                                               */
    /* ------------------------------------------------------------------ */

    public function isoImages(Server $server): array
    {
        $node = $server->node;
        $storage = $node->iso_storage_pool ?: $node->storage_pool;

        $content = (array) ($node->client()->get("nodes/{$node->proxmox_node_name}/storage/{$storage}/content", [
            'content' => 'iso',
        ]) ?? []);

        return array_values(array_map(fn ($item) => [
            'volid' => $item['volid'] ?? null,
            'name' => basename((string) ($item['volid'] ?? '')),
            'size' => (int) ($item['size'] ?? 0),
        ], $content));
    }

    public function mountIso(Server $server, string $volumeId): void
    {
        $this->update($server, ['ide0' => "{$volumeId},media=cdrom"]);
    }

    public function unmountIso(Server $server): void
    {
        $this->update($server, ['ide0' => 'none,media=cdrom']);
    }

    public function setBootOrder(Server $server, string $order): void
    {
        $this->update($server, ['boot' => "order={$order}"]);
    }

    /* ------------------------------------------------------------------ */
    /* Firewall                                                            */
    /* ------------------------------------------------------------------ */

    public function firewallOptions(Server $server): array
    {
        return (array) ($server->node->client()->get($this->base($server).'/firewall/options') ?? []);
    }

    public function setFirewallOptions(Server $server, array $payload): void
    {
        $server->node->client()->put($this->base($server).'/firewall/options', $payload);
    }

    public function firewallRules(Server $server): array
    {
        return (array) ($server->node->client()->get($this->base($server).'/firewall/rules') ?? []);
    }

    public function createFirewallRule(Server $server, array $payload): void
    {
        $server->node->client()->post($this->base($server).'/firewall/rules', $payload);
    }

    public function deleteFirewallRule(Server $server, int $position): void
    {
        $server->node->client()->delete($this->base($server)."/firewall/rules/{$position}");
    }

    /* ------------------------------------------------------------------ */
    /* Tasks                                                               */
    /* ------------------------------------------------------------------ */

    /** Proxmox task history filtered to this VM. */
    public function proxmoxTasks(Server $server, int $limit = 30): array
    {
        $node = $server->node;

        return (array) ($node->client()->get("nodes/{$node->proxmox_node_name}/tasks", [
            'vmid' => $server->vmid,
            'limit' => $limit,
        ]) ?? []);
    }

    public function taskLog(Server $server, string $upid, int $limit = 400): array
    {
        $node = $server->node;

        return (array) ($node->client()->get(
            "nodes/{$node->proxmox_node_name}/tasks/".rawurlencode($upid).'/log',
            ['limit' => $limit],
        ) ?? []);
    }

    /**
     * Reinstall: destroy the current disk set and clone the chosen template
     * back onto the same VMID, keeping allocations and the database record.
     */
    public function reinstall(Server $server, string $template, ?string $rootPassword = null, ?string $sshKeys = null): void
    {
        $node = $server->node;
        $server->forceFill(['status' => Server::STATUS_INSTALLING, 'is_locked' => true])->save();

        try {
            try {
                $this->servers->power($server, 'stop');
            } catch (ProxmoxRequestException) {
                // Already stopped.
            }

            $templateVmid = $this->templateVmid($server, $template);

            if ($templateVmid === null) {
                throw new ProxmoxRequestException("No Proxmox template matching [{$template}] was found on node {$node->name}.");
            }

            $upid = $node->client()->delete("nodes/{$node->proxmox_node_name}/qemu/{$server->vmid}", [
                'purge' => 0,
                'destroy-unreferenced-disks' => 1,
            ]);
            $this->servers->waitForTask($node, $upid, 900);

            $clone = $node->client()->post("nodes/{$node->proxmox_node_name}/qemu/{$templateVmid}/clone", [
                'newid' => $server->vmid,
                'name' => $server->uuid_short,
                'full' => 1,
                'storage' => $node->storage_pool,
            ]);
            $this->servers->waitForTask($node, $clone, 3600);

            $this->update($server, [
                'cores' => $server->cpu_cores,
                'sockets' => 1,
                'memory' => $server->memory_mb,
                'onboot' => 1,
                'agent' => 'enabled=1',
            ]);

            $this->syncNetworkFromAllocations($server);
            $this->updateCloudInit($server, [
                'root_password' => $rootPassword,
                'ssh_keys' => $sshKeys,
            ]);

            $server->forceFill([
                'template' => $template,
                'status' => Server::STATUS_READY,
                'is_locked' => false,
                'installed_at' => now(),
            ])->save();
        } catch (ProxmoxRequestException $e) {
            $server->forceFill(['status' => Server::STATUS_INSTALL_FAILED, 'is_locked' => false])->save();

            throw $e;
        }
    }

    private function templateVmid(Server $server, string $template): ?int
    {
        $node = $server->node;
        $vms = (array) ($node->client()->get("nodes/{$node->proxmox_node_name}/qemu") ?? []);

        foreach ($vms as $vm) {
            if ((int) ($vm['template'] ?? 0) === 1 && str_contains((string) ($vm['name'] ?? ''), $template)) {
                return (int) $vm['vmid'];
            }
        }

        return null;
    }
}
