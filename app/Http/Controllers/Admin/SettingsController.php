<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\SettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly AuditLogger $audit,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Settings/Index', [
            'settings' => $this->settings->frontendPayload(),
            'advanced' => [
                'allowed_email_domains' => $this->settings->get('registration.allowed_email_domains', []),
                'default_role' => $this->settings->get('registration.default_role', 'user'),
                'require_email_verification' => (bool) $this->settings->get('registration.require_email_verification', true),
                'discord_required_guild_id' => $this->settings->get('auth.discord.required_guild_id'),
                'discord_allow_account_creation' => (bool) $this->settings->get('auth.discord.allow_account_creation', true),
                'discord_configured' => (bool) config('services.discord.client_id') && (bool) config('services.discord.client_secret'),
            ],
            'widgetCatalogue' => self::WIDGETS,
        ]);
    }

    public const WIDGETS = [
        'resource-summary' => 'Resource summary tiles',
        'node-health' => 'Node health and capacity',
        'recent-activity' => 'Recent audit activity',
        'server-list' => 'Latest servers table',
        'capacity-chart' => 'Cluster capacity chart',
        'bandwidth-chart' => 'Bandwidth consumption chart',
        'task-queue' => 'Running Proxmox tasks',
        'quick-actions' => 'Quick action shortcuts',
    ];

    /** Panel name, tagline, social/OG description. */
    public function updateBranding(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'panel_name' => ['required', 'string', 'max:60'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'social_description' => ['nullable', 'string', 'max:300'],
        ]);

        $this->settings->setMany([
            'branding.panel_name' => $data['panel_name'],
            'branding.tagline' => $data['tagline'],
            'branding.social_description' => $data['social_description'],
        ], group: 'branding', isPublic: true);

        $this->audit->log('admin.settings.branding_updated', null, $data);

        return back()->with('success', 'Branding updated.');
    }

    public function updateLogo(Request $request): RedirectResponse
    {
        $request->validate([
            'logo' => ['required', 'file', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
        ]);

        $this->replaceAsset('branding.logo_path', $request->file('logo'), 'branding');
        $this->audit->log('admin.settings.logo_updated');

        return back()->with('success', 'Logo updated.');
    }

    public function updateFavicon(Request $request): RedirectResponse
    {
        $request->validate([
            'favicon' => ['required', 'file', 'mimes:png,ico,svg,webp', 'max:1024'],
        ]);

        $this->replaceAsset('branding.favicon_path', $request->file('favicon'), 'branding');
        $this->audit->log('admin.settings.favicon_updated');

        return back()->with('success', 'Favicon updated.');
    }

    public function removeAsset(Request $request): RedirectResponse
    {
        $key = $request->validate([
            'asset' => ['required', 'in:logo,favicon'],
        ])['asset'];

        $settingKey = "branding.{$key}_path";

        if ($path = $this->settings->get($settingKey)) {
            Storage::disk('public')->delete($path);
        }

        $this->settings->forget($settingKey);

        return back()->with('success', ucfirst($key).' removed.');
    }

    /** Colour changer. */
    public function updateTheme(Request $request): RedirectResponse
    {
        $hex = ['required', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'];

        $data = $request->validate([
            'brand' => $hex,
            'brand_soft' => $hex,
            'brand_contrast' => $hex,
            'accent' => $hex,
            'radius' => ['required', 'string', 'max:12'],
            'font' => ['required', 'string', 'max:60'],
            'default_mode' => ['required', 'in:light,dark,system'],
            'allow_user_mode_switch' => ['boolean'],
        ]);

        $this->settings->setMany([
            'theme.brand' => $data['brand'],
            'theme.brand_soft' => $data['brand_soft'],
            'theme.brand_contrast' => $data['brand_contrast'],
            'theme.accent' => $data['accent'],
            'theme.radius' => $data['radius'],
            'theme.font' => $data['font'],
            'theme.default_mode' => $data['default_mode'],
            'theme.allow_user_mode_switch' => (bool) ($data['allow_user_mode_switch'] ?? true),
        ], group: 'theme', isPublic: true);

        $this->audit->log('admin.settings.theme_updated', null, $data);

        return back()->with('success', 'Theme updated.');
    }

    /** Layout editor: navigation style, density, container width, dashboard widget grid. */
    public function updateLayout(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'navigation' => ['required', 'in:sidebar,topbar,rail'],
            'density' => ['required', 'in:compact,comfortable,spacious'],
            'container' => ['required', 'in:boxed,wide,fluid'],
            'dashboard_widgets' => ['array'],
            'dashboard_widgets.*.key' => ['required', 'string', 'in:'.implode(',', array_keys(self::WIDGETS))],
            'dashboard_widgets.*.span' => ['required', 'integer', 'in:3,4,6,8,9,12'],
            'dashboard_widgets.*.enabled' => ['required', 'boolean'],
        ]);

        $this->settings->setMany([
            'layout.navigation' => $data['navigation'],
            'layout.density' => $data['density'],
            'layout.container' => $data['container'],
            'layout.dashboard_widgets' => array_values($data['dashboard_widgets'] ?? []),
        ], group: 'layout', isPublic: true);

        $this->audit->log('admin.settings.layout_updated', null, ['navigation' => $data['navigation']]);

        return back()->with('success', 'Layout saved.');
    }

    /** Registration + Discord controls. */
    public function updateAccess(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'registration_enabled' => ['required', 'boolean'],
            'require_email_verification' => ['required', 'boolean'],
            'allowed_email_domains' => ['array'],
            'allowed_email_domains.*' => ['string', 'max:120'],
            'default_role' => ['required', 'string', 'exists:roles,name'],
            'discord_enabled' => ['required', 'boolean'],
            'discord_required_guild_id' => ['nullable', 'string', 'max:32'],
            'discord_allow_account_creation' => ['required', 'boolean'],
        ]);

        $this->settings->setMany([
            'registration.enabled' => (bool) $data['registration_enabled'],
            'registration.require_email_verification' => (bool) $data['require_email_verification'],
            'registration.allowed_email_domains' => array_values(array_filter($data['allowed_email_domains'] ?? [])),
            'registration.default_role' => $data['default_role'],
            'auth.discord.enabled' => (bool) $data['discord_enabled'],
            'auth.discord.required_guild_id' => $data['discord_required_guild_id'],
            'auth.discord.allow_account_creation' => (bool) $data['discord_allow_account_creation'],
        ], group: 'access', isPublic: false);

        $this->audit->log('admin.settings.access_updated', null, [
            'registration_enabled' => $data['registration_enabled'],
        ]);

        return back()->with('success', 'Access settings saved.');
    }

    private function replaceAsset(string $settingKey, $file, string $directory): void
    {
        if ($existing = $this->settings->get($settingKey)) {
            Storage::disk('public')->delete($existing);
        }

        $path = $file->store($directory, 'public');
        $this->settings->set($settingKey, $path, group: 'branding', isPublic: true);
    }
}
