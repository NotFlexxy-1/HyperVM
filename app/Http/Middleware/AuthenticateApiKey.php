<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $authorization = (string) $request->header('Authorization');

        if (! preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            return response()->json(['error' => 'API authentication required.'], 401);
        }

        $token = trim($matches[1]);

        if (! preg_match('/^hypervm_([a-z0-9]{16})\.([A-Za-z0-9]{48})$/', $token, $matches)) {
            return response()->json(['error' => 'Invalid API token.'], 401);
        }

        [, $identifier, $secret] = $matches;

        $apiKey = ApiKey::query()
            ->with('user')
            ->where('identifier', $identifier)
            ->first();

        if (! $apiKey || ! $apiKey->user) {
            return response()->json(['error' => 'Invalid API token.'], 401);
        }

        if ($apiKey->isExpired()) {
            return response()->json(['error' => 'API token has expired.'], 401);
        }

        if (! Hash::check($secret, $apiKey->token_hash)) {
            return response()->json(['error' => 'Invalid API token.'], 401);
        }

        if ($apiKey->user->is_suspended) {
            return response()->json(['error' => 'Account suspended.'], 403);
        }

        $allowedIps = array_values(array_filter((array) $apiKey->allowed_ips));

        if ($allowedIps !== [] && ! in_array($request->ip(), $allowedIps, true)) {
            return response()->json(['error' => 'IP address is not allowed for this API key.'], 403);
        }

        $apiKey->forceFill([
            'last_used_at' => now(),
        ])->save();

        $request->attributes->set('hypervm_api_key', $apiKey);
        $request->setUserResolver(fn () => $apiKey->user);

        return $next($request);
    }
}
