<?php

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records every state-changing authenticated request. Read requests are not
 * logged to keep the audit table meaningful.
 */
class RecordAuditTrail
{
    private const IGNORED = ['login', 'logout', 'password.confirm'];

    public function __construct(private readonly AuditLogger $logger) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->user() || $request->isMethod('GET')) {
            return $response;
        }

        if ($response->getStatusCode() >= 400) {
            return $response;
        }

        $route = $request->route()?->getName();

        if (! $route || in_array($route, self::IGNORED, true)) {
            return $response;
        }

        $this->logger->log($route, null, [
            'method' => $request->method(),
            'path' => $request->path(),
            'input' => collect($request->except([
                'password', 'password_confirmation', 'current_password', 'token_secret',
                'root_password', 'cipassword',
            ]))->take(25)->all(),
        ]);

        return $response;
    }
}
