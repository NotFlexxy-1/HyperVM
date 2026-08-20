<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AddressPool extends Model
{
    protected $fillable = ['name', 'description'];

    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class);
    }

    public function nodes(): BelongsToMany
    {
        return $this->belongsToMany(Node::class);
    }
}
