<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'cpu_cores', 'memory_mb', 'disk_mb', 'bandwidth_gb',
        'disk_read_bps', 'disk_write_bps', 'network_speed_mbps', 'snapshot_limit',
        'backup_limit', 'allocation_limit', 'monthly_price', 'currency', 'is_public', 'sort_order',
    ];

    protected $casts = ['is_public' => 'boolean', 'monthly_price' => 'decimal:2'];

    public function servers(): HasMany
    {
        return $this->hasMany(Server::class);
    }
}
