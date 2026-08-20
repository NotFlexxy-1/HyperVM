<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    private const CHALLENGE_KEY = 'hypervm.2fa.user';

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly TwoFactorService $totp,
    ) {}

    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => true,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'identifier' => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ]);

        $key = Str::lower($credentials['identifier']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'identifier' => 'Too many login attempts. Try again in '.RateLimiter::availableIn($key).' seconds.',
            ]);
        }

        $field = filter_var($credentials['identifier'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $remember = (bool) ($credentials['remember'] ?? false);

        if (! Auth::attempt([$field => $credentials['identifier'], 'password' => $credentials['password']], $remember)) {
            RateLimiter::hit($key, 300);

            throw ValidationException::withMessages([
                'identifier' => 'These credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($key);
        $user = $request->user();

        if ($user->is_suspended) {
            Auth::logout();

            throw ValidationException::withMessages([
                'identifier' => 'This account has been suspended.',
            ]);
        }

        // Second factor: drop the session back to guest and hold the user id
        // until a valid TOTP or recovery code is supplied.
        if ($user->two_factor_confirmed_at !== null) {
            Auth::logout();
            $request->session()->put(self::CHALLENGE_KEY, ['id' => $user->id, 'remember' => $remember]);

            return redirect()->route('two-factor.challenge');
        }

        return $this->completeLogin($request, $user);
    }

    public function challenge(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has(self::CHALLENGE_KEY)) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function verifyChallenge(Request $request): RedirectResponse
    {
        $pending = $request->session()->get(self::CHALLENGE_KEY);

        if (! $pending) {
            return redirect()->route('login');
        }

        $data = $request->validate([
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        /** @var User $user */
        $user = User::findOrFail($pending['id']);
        $throttle = '2fa|'.$user->id.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttle, 5)) {
            throw ValidationException::withMessages([
                'code' => 'Too many attempts. Try again in '.RateLimiter::availableIn($throttle).' seconds.',
            ]);
        }

        $verified = false;

        if (! empty($data['code'])) {
            $verified = $this->totp->verify((string) $user->two_factor_secret, $data['code']);
        }

        if (! $verified && ! empty($data['recovery_code'])) {
            $codes = (array) ($user->two_factor_recovery_codes ?? []);
            $index = array_search(trim($data['recovery_code']), $codes, true);

            if ($index !== false) {
                unset($codes[$index]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();
                $verified = true;
            }
        }

        if (! $verified) {
            RateLimiter::hit($throttle, 300);

            throw ValidationException::withMessages(['code' => 'That code is not valid.']);
        }

        RateLimiter::clear($throttle);
        $request->session()->forget(self::CHALLENGE_KEY);

        Auth::login($user, (bool) ($pending['remember'] ?? false));

        return $this->completeLogin($request, $user);
    }

    private function completeLogin(Request $request, User $user): RedirectResponse
    {
        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $this->audit->log('auth.login', $user);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->audit->log('auth.logout', $request->user());

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
