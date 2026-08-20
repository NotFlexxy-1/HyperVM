<?php

namespace App\Http\Middleware;

use App\Models\Server;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureServerIsAccessible
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Server|null $server */
        $server = $request->route('server');
        $user = $request->user();

        if (! $server || ! $user) {
            abort(404);
        }

        $isOwner = $server->owner_id === $user->id;
        $isSubuser = $server->subusers()->whereKey($user->id)->exists();

        if (! $isOwner && ! $isSubuser && ! $user->can('server.view.all')) {
            abort(403, 'You do not have access to this server.');
        }

        return $next($request);
    }
}
