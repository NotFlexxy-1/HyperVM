<?php

namespace Tests\Feature;

use App\Services\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_falls_back_to_config_defaults(): void
    {
        $settings = app(SettingsRepository::class);

        $this->assertSame(config('hypervm.theme.brand'), $settings->get('theme.brand'));
    }

    public function test_it_persists_and_casts_values(): void
    {
        $settings = app(SettingsRepository::class);

        $settings->set('registration.enabled', true);
        $settings->set('layout.dashboard_widgets', [['key' => 'server-list', 'span' => 12, 'enabled' => true]]);

        $this->assertTrue($settings->isRegistrationEnabled());
        $this->assertSame('server-list', $settings->layoutWidgets()[0]['key']);
    }
}
