<?php

namespace App\Providers;

use App\Services\AuditLogger;
use App\Services\Proxmox\BackupService;
use App\Services\Proxmox\NodeService;
use App\Services\Proxmox\ServerService;
use App\Services\SettingsRepository;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsRepository::class);
        $this->app->singleton(AuditLogger::class);
        $this->app->singleton(NodeService::class);
        $this->app->singleton(ServerService::class);
        $this->app->singleton(BackupService::class);
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Password::defaults(function () {
            $rules = config('hypervm.auth.password');
            $password = Password::min((int) $rules['min_length']);

            if ($rules['require_mixed_case']) {
                $password->mixedCase();
            }
            if ($rules['require_numbers']) {
                $password->numbers();
            }
            if ($rules['require_symbols']) {
                $password->symbols();
            }
            if ($rules['check_compromised']) {
                $password->uncompromised();
            }

            return $password;
        });
    }
}
