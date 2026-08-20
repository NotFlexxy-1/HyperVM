<?php

namespace App\Services\Proxmox;

use App\Models\Node;
use App\Models\NodeMetric;
use Illuminate\Support\Facades\Cache;

class NodeService
{
    /** Live status of the PVE node (cpu, memory, rootfs, uptime, kernel version). */
    public function status(Node $node): array
    {
        return Cache::remember(
            "hypervm:node:{$node->id}:status",
            (int) config('hypervm.proxmox.metrics_cache_seconds', 10),
            fn () => (array) $node->client()->get("nodes/{$node->proxmox_node_name}/status"),
        );
    }

    public function version(Node $node): array
    {
        return (array) $node->client()->get('version');
    }

    /** All VMs known to Proxmox on this node, including those not managed by HyperVM. */
    public function virtualMachines(Node $node): array
    {
        return (array) ($node->client()->get("nodes/{$node->proxmox_node_name}/qemu") ?? []);
    }

    public function storages(Node $node): array
    {
        return (array) ($node->client()->get("nodes/{$node->proxmox_node_name}/storage") ?? []);
    }

    public function networkBridges(Node $node): array
    {
        $interfaces = (array) ($node->client()->get("nodes/{$node->proxmox_node_name}/network", ['type' => 'bridge']) ?? []);

        return array_values(array_map(fn ($i) => $i['iface'], $interfaces));
    }

    public function tasks(Node $node, int $limit = 50): array
    {
        return (array) ($node->client()->get("nodes/{$node->proxmox_node_name}/tasks", [
            'limit' => $limit,
            'errors' => 0,
        ]) ?? []);
    }

    /** Persist a metrics sample; called by the hypervm:collect-metrics scheduled command. */
    public function recordMetrics(Node $node): NodeMetric
    {
        $status = $this->status($node);

        $metric = $node->metrics()->create([
            'cpu_usage' => round(((float) ($status['cpu'] ?? 0)) * 100, 3),
            'memory_used_bytes' => (int) ($status['memory']['used'] ?? 0),
            'memory_total_bytes' => (int) ($status['memory']['total'] ?? 0),
            'disk_used_bytes' => (int) ($status['rootfs']['used'] ?? 0),
            'disk_total_bytes' => (int) ($status['rootfs']['total'] ?? 0),
            'uptime_seconds' => (int) ($status['uptime'] ?? 0),
            'recorded_at' => now(),
        ]);

        $node->forceFill(['last_seen_at' => now()])->save();

        return $metric;
    }

    public function nextAvailableVmid(Node $node): int
    {
        $range = config('hypervm.proxmox.vmid_range');
        $used = collect($this->virtualMachines($node))->pluck('vmid')->map(fn ($v) => (int) $v)->all();
        $reserved = $node->servers()->pluck('vmid')->map(fn ($v) => (int) $v)->all();
        $taken = array_flip(array_merge($used, $reserved));

        for ($vmid = (int) $range['start']; $vmid <= (int) $range['end']; $vmid++) {
            if (! isset($taken[$vmid])) {
                return $vmid;
            }
        }

        throw new \RuntimeException('No free VMID available in the configured range.');
    }
}
