<?php

namespace Database\Seeders;

use App\Services\SettingsRepository;
use Illuminate\Database\Seeder;

/**
 * Writes the shipped defaults from config/hypervm.php into the settings table
 * so administrators immediately see editable values in the settings UI.
 */
class SettingsSeeder extends Seeder
{
    public function run(SettingsRepository $settings): void
    {
        $settings->setMany([
            'branding.panel_name' => config('hypervm.branding.panel_name'),
            'branding.tagline' => config('hypervm.branding.tagline'),
            'branding.social_description' => config('hypervm.branding.social_description'),
        ], group: 'branding', isPublic: true);

        $settings->setMany([
            'theme.brand' => config('hypervm.theme.brand'),
            'theme.brand_soft' => config('hypervm.theme.brand_soft'),
            'theme.brand_contrast' => config('hypervm.theme.brand_contrast'),
            'theme.accent' => config('hypervm.theme.accent'),
            'theme.radius' => config('hypervm.theme.radius'),
            'theme.font' => config('hypervm.theme.font'),
            'theme.default_mode' => config('hypervm.theme.default_mode'),
            'theme.allow_user_mode_switch' => config('hypervm.theme.allow_user_mode_switch'),
        ], group: 'theme', isPublic: true);

        $settings->setMany([
            'layout.navigation' => config('hypervm.layout.navigation'),
            'layout.density' => config('hypervm.layout.density'),
            'layout.container' => config('hypervm.layout.container'),
            'layout.dashboard_widgets' => config('hypervm.layout.dashboard_widgets'),
        ], group: 'layout', isPublic: true);

        $settings->setMany([
            'registration.enabled' => config('hypervm.registration.enabled'),
            'registration.require_email_verification' => config('hypervm.registration.require_email_verification'),
            'registration.allowed_email_domains' => config('hypervm.registration.allowed_email_domains'),
            'registration.default_role' => config('hypervm.registration.default_role'),
            'auth.discord.enabled' => config('hypervm.auth.discord.enabled'),
            'auth.discord.allow_account_creation' => config('hypervm.auth.discord.allow_account_creation'),
        ], group: 'access');
    }
}
