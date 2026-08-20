<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'uuid', 'name', 'username', 'email', 'password', 'discord_id', 'discord_username',
        'avatar_url', 'is_suspended', 'force_password_change', 'preferences',
    ];

    protected $hidden = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'is_suspended' => 'boolean',
            'force_password_change' => 'boolean',
            'preferences' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            $user->uuid ??= (string) \Illuminate\Support\Str::uuid();
        });
    }

    public function servers(): HasMany
    {
        return $this->hasMany(Server::class, 'owner_id');
    }

    public function sharedServers(): BelongsToMany
    {
        return $this->belongsToMany(Server::class, 'server_user')->withPivot('permissions')->withTimestamps();
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function accessibleServers()
    {
        return Server::query()->where(function ($query) {
            $query->where('owner_id', $this->id)
                ->orWhereHas('subusers', fn ($q) => $q->where('users.id', $this->id));
        });
    }
}
