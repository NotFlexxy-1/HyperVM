<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    protected $fillable = ['short_code', 'name', 'country_code', 'description'];

    public function nodes(): HasMany
    {
        return $this->hasMany(Node::class);
    }
}
