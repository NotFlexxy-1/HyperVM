<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Server extends Model
{
    public const STATUS_READY = 'ready';
    public const STATUS_INSTALLING = 'installing';
    public const STATUS_INSTALL_FAILED = 'install_failed';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_RESTORING = 'restoring';
    public const STATUS_DELETING = 'deleting';

    protected $fillable = [
        'uuid', 'uuid_short', 'name', 'description', 'owner_id', 'node_id', 'plan_id', 'vmid',
        'status', 'cpu_cores', 'memory_mb', 'disk_mb', 'bandwidth_gb', 'snapshot_limit',
        'backup_limit', 'network_speed_mbps', 'template', 'os_type', 'is_locked',
    ];

    protected function casts(): array
    {
        return [
            'is_locked' => 'boolean',
            'suspended_at' => 'datetime',
            'installed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Server $server) {
            $server->uuid ??= (string) Str::uuid();
            $server->uuid_short ??= substr(str_replace('-', '', $server->uuid), 0, 12);
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class);
    }

    public function primaryAllocation(): BelongsTo|HasMany
    {
        return $this->hasMany(Allocation::class)->orderBy('id');
    }

    public function subusers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'server_user')->withPivot('permissions')->withTimestamps();
    }

    public function backups(): HasMany
    {
        return $this->hasMany(ServerBackup::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(ServerSnapshot::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ServerTask::class);
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function canBeControlled(): bool
    {
        return $this->status === self::STATUS_READY && ! $this->is_locked;
    }

    public function getRouteKeyName(): string
    {
        return 'uuid_short';
    }
}
