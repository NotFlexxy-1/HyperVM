<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerSnapshot extends Model
{
    protected $fillable = ['server_id', 'name', 'description', 'include_ram', 'is_successful'];

    protected $casts = ['include_ram' => 'boolean', 'is_successful' => 'boolean'];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
