<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class DiscordController extends Controller
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly AuditLogger $audit,
    ) {}

    public function redirect(): RedirectResponse
    {
        abort_unless($this->discordEnabled(), 404);

        return Socialite::driver('discord')
            ->scopes($this->requiredGuild() ? ['identify', 'email', 'guilds'] : ['identify', 'email'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless($this->discordEnabled(), 404);

        try {
            $discordUser = Socialite::driver('discord')->user();
        } catch (\Throwable $e) {
            return redirect()->route('login')->with('error', 'Discord authentication failed: '.$e->getMessage());
        }

        if ($guildId = $this->requiredGuild()) {
            if (! $this->userIsInGuild($discordUser->token, $guildId)) {
                return redirect()->route('login')->with('error', 'You must be a member of the required Discord server to sign in.');
            }
        }

        $user = User::where('discord_id', $discordUser->getId())->first();

        if (! $user && $discordUser->getEmail()) {
            $user = User::where('email', $discordUser->getEmail())->first();
        }

        if (! $user) {
            if (! $this->settings->get('auth.discord.allow_account_creation', true) || ! $this->settings->isRegistrationEnabled()) {
                return redirect()->route('login')->with('error', 'No account is linked to this Discord profile and registration is disabled.');
            }

            $user = User::create([
                'name' => $discordUser->getName() ?: $discordUser->getNickname() ?: 'Discord User',
                'username' => $this->uniqueUsername($discordUser->getNickname() ?: $discordUser->getId()),
                'email' => $discordUser->getEmail() ?: $discordUser->getId().'@discord.local',
                'password' => null,
                'email_verified_at' => now(),
            ]);

            $user->assignRole((string) $this->settings->get('registration.default_role', 'user'));
        }

        if ($user->is_suspended) {
            return redirect()->route('login')->with('error', 'This account has been suspended.');
        }

        $user->forceFill([
            'discord_id' => $discordUser->getId(),
            'discord_username' => $discordUser->getNickname() ?: $discordUser->getName(),
            'avatar_url' => $discordUser->getAvatar(),
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        Auth::login($user, remember: true);
        $request->session()->regenerate();
        $this->audit->log('auth.discord_login', $user);

        return redirect()->intended(route('dashboard'));
    }

    private function userIsInGuild(string $token, string $guildId): bool
    {
        $response = Http::withToken($token)
            ->acceptJson()
            ->get('https://discord.com/api/users/@me/guilds');

        if ($response->failed()) {
            return false;
        }

        return collect($response->json())->contains(fn ($guild) => (string) ($guild['id'] ?? '') === $guildId);
    }

    private function uniqueUsername(string $base): string
    {
        $username = Str::slug(Str::limit($base, 30, ''), '_') ?: 'user';
        $candidate = $username;

        while (User::where('username', $candidate)->exists()) {
            $candidate = $username.'_'.Str::lower(Str::random(4));
        }

        return $candidate;
    }

    private function requiredGuild(): ?string
    {
        return $this->settings->get('auth.discord.required_guild_id') ?: null;
    }

    private function discordEnabled(): bool
    {
        return (bool) $this->settings->get('auth.discord.enabled', false)
            && config('services.discord.client_id')
            && config('services.discord.client_secret');
    }
}
