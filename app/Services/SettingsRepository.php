<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

/**
 * Runtime configuration store. Any key that is absent from the `settings`
 * table falls back to the matching value in config/hypervm.php.
 */
class SettingsRepository
{
    private const CACHE_KEY = 'hypervm:settings';

    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return Setting::query()->get()->mapWithKeys(fn (Setting $s) => [
                $s->key => $s->typed_value,
            ])->all();
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $stored = $this->all();

        if (array_key_exists($key, $stored)) {
            return $stored[$key];
        }

        return config("hypervm.{$key}", $default);
    }

    public function set(string $key, mixed $value, string $group = 'general', bool $isPublic = false): void
    {
        [$type, $stored] = match (true) {
            is_bool($value) => ['bool', $value ? '1' : '0'],
            is_int($value) => ['int', (string) $value],
            is_array($value) => ['json', json_encode($value)],
            default => ['string', $value === null ? null : (string) $value],
        };

        Setting::updateOrCreate(
            ['key' => $key],
            ['value' => $stored, 'type' => $type, 'group' => $group, 'is_public' => $isPublic],
        );

        $this->flush();
    }

    public function setMany(array $values, string $group = 'general', bool $isPublic = false): void
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $group, $isPublic);
        }
    }

    public function forget(string $key): void
    {
        Setting::where('key', $key)->delete();
        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** Everything the frontend needs to render branding, theme and layout. */
    public function frontendPayload(): array
    {
        return [
            'branding' => [
                'panel_name' => $this->get('branding.panel_name', config('hypervm.branding.panel_name')),
                'tagline' => $this->get('branding.tagline', config('hypervm.branding.tagline')),
                'social_description' => $this->get('branding.social_description', config('hypervm.branding.social_description')),
                'logo_url' => $this->assetUrl($this->get('branding.logo_path')),
                'favicon_url' => $this->assetUrl($this->get('branding.favicon_path')),
            ],
            'theme' => [
                'brand' => $this->get('theme.brand', config('hypervm.theme.brand')),
                'brand_soft' => $this->get('theme.brand_soft', config('hypervm.theme.brand_soft')),
                'brand_contrast' => $this->get('theme.brand_contrast', config('hypervm.theme.brand_contrast')),
                'accent' => $this->get('theme.accent', config('hypervm.theme.accent')),
                'radius' => $this->get('theme.radius', config('hypervm.theme.radius')),
                'font' => $this->get('theme.font', config('hypervm.theme.font')),
                'default_mode' => $this->get('theme.default_mode', config('hypervm.theme.default_mode')),
                'allow_user_mode_switch' => (bool) $this->get('theme.allow_user_mode_switch', config('hypervm.theme.allow_user_mode_switch')),
            ],
            'layout' => [
                'navigation' => $this->get('layout.navigation', config('hypervm.layout.navigation')),
                'density' => $this->get('layout.density', config('hypervm.layout.density')),
                'container' => $this->get('layout.container', config('hypervm.layout.container')),
                'dashboard_widgets' => $this->get('layout.dashboard_widgets', config('hypervm.layout.dashboard_widgets')),
            ],
            'registration' => [
                'enabled' => (bool) $this->get('registration.enabled', config('hypervm.registration.enabled')),
                'discord_enabled' => (bool) $this->get('auth.discord.enabled', config('hypervm.auth.discord.enabled')),
            ],
        ];
    }

    private function assetUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return str_starts_with($path, 'http') ? $path : asset('storage/'.ltrim($path, '/'));
    }

    public function isRegistrationEnabled(): bool
    {
        return (bool) $this->get('registration.enabled', config('hypervm.registration.enabled'));
    }

    public function layoutWidgets(): array
    {
        return Arr::wrap($this->get('layout.dashboard_widgets', config('hypervm.layout.dashboard_widgets')));
    }
}
