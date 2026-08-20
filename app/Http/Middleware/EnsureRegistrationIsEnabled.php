<?php

namespace App\Http\Middleware;

use App\Services\SettingsRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRegistrationIsEnabled
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->settings->isRegistrationEnabled()) {
            return redirect()->route('login')->with('error', 'Registration is currently disabled on this panel.');
        }

        return $next($request);
    }
}
