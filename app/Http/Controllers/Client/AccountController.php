<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Services\AuditLogger;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Client/Account', [
            'apiKeys' => $user->apiKeys()->latest()->get(['id', 'memo', 'identifier', 'permissions', 'allowed_ips', 'last_used_at', 'expires_at', 'created_at']),
            'apiPermissions' => Permissions::grouped(),
            'sessions' => DB::table('sessions')
                ->where('user_id', $user->id)
                ->orderByDesc('last_activity')
                ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
                ->map(fn ($session) => [
                    'id' => $session->id,
                    'ip_address' => $session->ip_address,
                    'user_agent' => $session->user_agent,
                    'last_activity' => $session->last_activity,
                    'is_current' => $session->id === $request->session()->getId(),
                ]),
            'activity' => $user->auditLogs()->latest()->limit(25)->get(['id', 'action', 'ip_address', 'metadata', 'created_at']),
            'security' => [
                'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
                'two_factor_pending' => $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null,
                'recovery_codes_remaining' => count((array) ($user->two_factor_recovery_codes ?? [])),
                'password_changed_at' => $user->password_changed_at,
                'last_login_at' => $user->last_login_at,
                'last_login_ip' => $user->last_login_ip,
                'discord_username' => $user->discord_username,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'alpha_dash', 'max:40', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => ['nullable', 'string'],
        ]);

        // Changing the login email always requires the current password.
        if ($data['email'] !== $user->email) {
            if (! $user->password || ! Hash::check((string) ($data['current_password'] ?? ''), $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => 'Confirm your current password to change the account email.',
                ]);
            }
        }

        $user->update([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
        ]);

        $this->audit->log('account.profile.updated');

        return back()->with('success', 'Profile updated.');
    }

    /** Persists per-user UI preferences (theme mode, sidebar state, widget order). */
    public function updatePreferences(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'theme_mode' => ['nullable', 'in:light,dark,system'],
            'sidebar_collapsed' => ['nullable', 'boolean'],
            'dashboard_widgets' => ['nullable', 'array'],
        ]);

        $request->user()->forceFill([
            'preferences' => array_merge($request->user()->preferences ?? [], array_filter($data, fn ($v) => $v !== null)),
        ])->save();

        return back();
    }

    public function storeApiKey(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'memo' => ['required', 'string', 'max:120'],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(Permissions::all())],
            'allowed_ips' => ['array'],
            'allowed_ips.*' => ['ip'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $identifier = Str::lower(Str::random(16));
        $secret = Str::random(48);

        ApiKey::create([
            'user_id' => $request->user()->id,
            'memo' => $data['memo'],
            'identifier' => $identifier,
            'token_hash' => Hash::make($secret),
            'permissions' => $data['permissions'] ?? [],
            'allowed_ips' => $data['allowed_ips'] ?? [],
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        $this->audit->log('account.api_key.created', null, ['identifier' => $identifier]);

        return back()->with('success', 'API key created: hypervm_'.$identifier.'.'.$secret.' (shown once)');
    }

    public function destroyApiKey(Request $request, ApiKey $apiKey): RedirectResponse
    {
        abort_unless($apiKey->user_id === $request->user()->id, 403);

        $apiKey->delete();

        $this->audit->log('account.api_key.revoked', null, ['identifier' => $apiKey->identifier]);

        return back()->with('success', 'API key revoked.');
    }

    public function destroySession(Request $request, string $session): RedirectResponse
    {
        abort_if($session === $request->session()->getId(), 422, 'You cannot revoke the session you are currently using.');

        DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->where('id', $session)
            ->delete();

        $this->audit->log('account.session.revoked');

        return back()->with('success', 'Session revoked.');
    }
}
