<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireApiKeyPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $apiKey = $request->attributes->get('hypervm_api_key');

        if (! $apiKey) {
            return response()->json(['error' => 'API authentication required.'], 401);
        }

        $granted = array_map('strval', (array) $apiKey->permissions);

        foreach ($permissions as $permission) {
            if (in_array($permission, $granted, true)) {
                return $next($request);
            }
        }

        return response()->json([
            'error' => 'This API key does not have the required permission.',
        ], 403);
    }
}
