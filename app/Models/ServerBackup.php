<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ServerBackup extends Model
{
    protected $fillable = [
        'uuid', 'server_id', 'name', 'volume_id', 'compression_type',
        'size_bytes', 'is_successful', 'is_locked', 'completed_at',
    ];

    protected $casts = [
        'is_successful' => 'boolean',
        'is_locked' => 'boolean',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $backup) => $backup->uuid ??= (string) Str::uuid());
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
