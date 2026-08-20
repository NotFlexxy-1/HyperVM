<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Discord\DiscordExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;

class SettingsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(
            SocialiteWasCalled::class,
            DiscordExtendSocialite::class
        );
    }
}
