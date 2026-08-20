<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SettingsRepository;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly AuditLogger $audit,
    ) {}

    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'min:3', 'max:40', 'alpha_dash', Rule::unique('users', 'username')],
            'email' => ['required', 'email:rfc,dns', 'max:190', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $allowedDomains = (array) $this->settings->get('registration.allowed_email_domains', []);

        if ($allowedDomains !== []) {
            $domain = strtolower(substr(strrchr($data['email'], '@') ?: '', 1));

            if (! in_array($domain, array_map('strtolower', $allowedDomains), true)) {
                throw ValidationException::withMessages([
                    'email' => 'Registrations are limited to the following domains: '.implode(', ', $allowedDomains),
                ]);
            }
        }

        $user = User::create($data + ['password_changed_at' => now()]);
        $user->assignRole((string) $this->settings->get('registration.default_role', 'user'));

        event(new Registered($user));
        $this->audit->log('auth.register', $user);

        Auth::login($user);

        return redirect()->route('dashboard');
    }
}
