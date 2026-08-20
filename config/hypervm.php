<?php

return [
    /*
     | Values here are compile-time defaults. Anything an administrator can
     | change at runtime lives in the `settings` table and is resolved through
     | App\Services\SettingsRepository, which falls back to these values.
     */
    'branding' => [
        'panel_name' => env('APP_NAME', 'HyperVM'),
        'tagline' => 'Virtual machine orchestration for Proxmox VE',
        'social_description' => 'HyperVM — deploy, monitor and control Proxmox VE virtual machines from one panel.',
        'logo_path' => null,
        'favicon_path' => null,
    ],

    'theme' => [
        'brand' => '#5b8cff',
        'brand_soft' => '#8fb0ff',
        'brand_contrast' => '#ffffff',
        'accent' => '#22d3ee',
        'radius' => '14px',
        'font' => 'Inter',
        'default_mode' => 'dark',
        'allow_user_mode_switch' => true,
    ],

    'layout' => [
        'navigation' => 'sidebar',      // sidebar | topbar | rail
        'density' => 'comfortable',     // compact | comfortable | spacious
        'container' => 'wide',          // boxed | wide | fluid
        'dashboard_widgets' => [
            ['key' => 'resource-summary', 'span' => 12, 'enabled' => true],
            ['key' => 'node-health', 'span' => 8, 'enabled' => true],
            ['key' => 'recent-activity', 'span' => 4, 'enabled' => true],
            ['key' => 'server-list', 'span' => 12, 'enabled' => true],
        ],
    ],

    'registration' => [
        'enabled' => (bool) env('HYPERVM_REGISTRATION_ENABLED', false),
        'require_email_verification' => true,
        'allowed_email_domains' => [],
        'default_role' => 'user',
    ],

    'auth' => [
        'discord' => [
            'enabled' => (bool) env('HYPERVM_DISCORD_LOGIN_ENABLED', true),
            'required_guild_id' => env('DISCORD_REQUIRED_GUILD_ID'),
            'allow_account_creation' => true,
        ],
        'password' => [
            'min_length' => 12,
            'require_mixed_case' => true,
            'require_numbers' => true,
            'require_symbols' => true,
            'check_compromised' => true,
        ],
    ],

    'proxmox' => [
        'timeout' => (int) env('HYPERVM_PROXMOX_TIMEOUT', 15),
        'verify_tls' => (bool) env('HYPERVM_PROXMOX_VERIFY_TLS', true),
        'metrics_cache_seconds' => (int) env('HYPERVM_METRICS_CACHE_SECONDS', 10),
        'vmid_range' => ['start' => 1000, 'end' => 99999],
        'default_os_type' => 'l26',
        'supported_templates' => [
            'ubuntu-22.04' => 'Ubuntu 22.04 LTS',
            'ubuntu-24.04' => 'Ubuntu 24.04 LTS',
            'debian-11' => 'Debian 11 (Bullseye)',
            'debian-12' => 'Debian 12 (Bookworm)',
        ],
    ],
];
