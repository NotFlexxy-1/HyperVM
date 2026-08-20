<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NodeMetric extends Model
{
    protected $fillable = [
        'node_id', 'cpu_usage', 'memory_used_bytes', 'memory_total_bytes',
        'disk_used_bytes', 'disk_total_bytes', 'uptime_seconds', 'recorded_at',
    ];

    protected $casts = ['recorded_at' => 'datetime', 'cpu_usage' => 'float'];

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }
}
