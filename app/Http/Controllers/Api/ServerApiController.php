<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ProxmoxRequestException;
use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Services\Proxmox\ServerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sanctum-authenticated REST API (routes/api.php, prefix /api/v1).
 */
class ServerApiController extends Controller
{
    public function __construct(private readonly ServerService $servers) {}

    public function index(Request $request): JsonResponse
    {
        $servers = $request->user()->accessibleServers()
            ->with(['node:id,name', 'allocations:id,server_id,address,cidr,gateway'])
            ->paginate((int) $request->integer('per_page', 25));

        return response()->json($servers);
    }

    public function show(Request $request, Server $server): JsonResponse
    {
        $this->assertAccess($request, $server);

        return response()->json([
            'data' => $server->load(['node:id,name', 'allocations', 'plan']),
        ]);
    }

    public function status(Request $request, Server $server): JsonResponse
    {
        $this->assertAccess($request, $server);

        try {
            return response()->json(['data' => $this->servers->status($server)]);
        } catch (ProxmoxRequestException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }

    public function power(Request $request, Server $server): JsonResponse
    {
        $this->assertAccess($request, $server);

        $action = $request->validate(['action' => ['required', 'in:start,stop,shutdown,reboot,reset']])['action'];

        abort_unless($server->canBeControlled(), 409, 'Server is locked or suspended.');

        try {
            $upid = $this->servers->power($server, $action);
            $this->servers->recordTask($server, "power.{$action}", $upid, $request->user()->id);
        } catch (ProxmoxRequestException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        return response()->json(['data' => ['action' => $action, 'upid' => $upid]]);
    }

    private function assertAccess(Request $request, Server $server): void
    {
        $user = $request->user();

        abort_unless(
            $server->owner_id === $user->id
                || $server->subusers()->whereKey($user->id)->exists()
                || $user->can('server.view.all'),
            403,
        );
    }
}
