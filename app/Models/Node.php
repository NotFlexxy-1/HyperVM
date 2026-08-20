<?php

namespace App\Models;

use App\Services\Proxmox\ProxmoxClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Node extends Model
{
    protected $fillable = [
        'uuid', 'location_id', 'name', 'fqdn', 'port', 'cluster', 'proxmox_node_name',
        'token_id', 'token_secret', 'verify_tls', 'storage_pool', 'backup_storage_pool',
        'iso_storage_pool', 'network_bridge', 'memory_mb', 'memory_overallocate', 'disk_mb',
        'disk_overallocate', 'cpu_cores', 'cpu_overallocate', 'vm_limit', 'is_maintenance',
        'is_deployable', 'notes',
    ];

    protected $hidden = ['token_secret'];

    protected function casts(): array
    {
        return [
            'token_secret' => 'encrypted',
            'verify_tls' => 'boolean',
            'is_maintenance' => 'boolean',
            'is_deployable' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (Node $node) => $node->uuid ??= (string) Str::uuid());
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function servers(): HasMany
    {
        return $this->hasMany(Server::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(NodeMetric::class);
    }

    public function addressPools(): BelongsToMany
    {
        return $this->belongsToMany(AddressPool::class);
    }

    public function getApiUrlAttribute(): string
    {
        return sprintf('https://%s:%d/api2/json', $this->fqdn, $this->port);
    }

    public function client(): ProxmoxClient
    {
        return ProxmoxClient::forNode($this);
    }

    /** Allocatable memory including the over-allocation percentage. */
    public function allocatableMemoryMb(): int
    {
        return (int) round($this->memory_mb * (1 + $this->memory_overallocate / 100));
    }

    public function allocatableDiskMb(): int
    {
        return (int) round($this->disk_mb * (1 + $this->disk_overallocate / 100));
    }

    public function allocatableCpuCores(): int
    {
        return (int) round($this->cpu_cores * (1 + $this->cpu_overallocate / 100));
    }

    public function allocatedResources(): array
    {
        $row = $this->servers()
            ->selectRaw('COALESCE(SUM(memory_mb),0) as memory, COALESCE(SUM(disk_mb),0) as disk, COALESCE(SUM(cpu_cores),0) as cpu, COUNT(*) as servers')
            ->first();

        return [
            'memory_mb' => (int) $row->memory,
            'disk_mb' => (int) $row->disk,
            'cpu_cores' => (int) $row->cpu,
            'servers' => (int) $row->servers,
        ];
    }

    public function hasCapacityFor(int $memoryMb, int $diskMb, int $cpuCores): bool
    {
        $used = $this->allocatedResources();

        if ($this->vm_limit !== null && $used['servers'] >= $this->vm_limit) {
            return false;
        }

        return $used['memory_mb'] + $memoryMb <= $this->allocatableMemoryMb()
            && $used['disk_mb'] + $diskMb <= $this->allocatableDiskMb()
            && $used['cpu_cores'] + $cpuCores <= $this->allocatableCpuCores();
    }
}
