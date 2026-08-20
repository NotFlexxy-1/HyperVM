<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Allocation extends Model
{
    protected $fillable = [
        'address_pool_id', 'node_id', 'server_id', 'type', 'address', 'cidr',
        'gateway', 'mac_address', 'vlan_id', 'label',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function pool(): BelongsTo
    {
        return $this->belongsTo(AddressPool::class, 'address_pool_id');
    }

    public function scopeAvailable($query)
    {
        return $query->whereNull('server_id');
    }

    public function getCidrAddressAttribute(): string
    {
        return "{$this->address}/{$this->cidr}";
    }
}
