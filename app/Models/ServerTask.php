<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerTask extends Model
{
    protected $fillable = ['server_id', 'user_id', 'action', 'upid', 'status', 'payload', 'output', 'finished_at'];

    protected $casts = ['payload' => 'array', 'finished_at' => 'datetime'];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
