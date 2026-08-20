<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\SettingsRepository;
use App\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TwoFactorController extends Controller
{
    public function __construct(
        private readonly TwoFactorService $totp,
        private readonly SettingsRepository $settings,
        private readonly AuditLogger $audit,
    ) {}

    /** Generates (but does not yet enable) a secret and stores it on the user. */
    public function create(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if($user->two_factor_confirmed_at !== null, 422, 'Two-factor authentication is already enabled.');

        $secret = $this->totp->generateSecret();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return response()->json([
            'secret' => $secret,
            'uri' => $this->totp->provisioningUri(
                $user,
                $secret,
                (string) $this->settings->get('branding.panel_name', config('hypervm.branding.panel_name')),
            ),
        ]);
    }

    /** Confirms the secret with a live code and returns the recovery codes. */
    public function confirm(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate(['code' => ['required', 'string']]);

        abort_if(! $user->two_factor_secret, 422, 'Generate a secret first.');

        if (! $this->totp->verify($user->two_factor_secret, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'That code is not valid.']);
        }

        $codes = $this->totp->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->audit->log('account.2fa.enabled');

        return back()->with('success', 'Two-factor authentication enabled. Recovery codes: '.implode(', ', $codes));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate(['password' => ['required', 'string']]);

        if (! $user->password || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => 'Your password is incorrect.']);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->audit->log('account.2fa.disabled');

        return back()->with('success', 'Two-factor authentication disabled.');
    }

    /** Regenerates recovery codes for an already-enabled account. */
    public function recoveryCodes(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_if($user->two_factor_confirmed_at === null, 422, 'Two-factor authentication is not enabled.');

        $codes = $this->totp->generateRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $codes])->save();

        $this->audit->log('account.2fa.recovery_codes_regenerated');

        return back()->with('success', 'New recovery codes: '.implode(', ', $codes));
    }
}
