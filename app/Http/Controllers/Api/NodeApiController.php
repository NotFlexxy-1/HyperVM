<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Node;
use App\Services\Proxmox\NodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NodeApiController extends Controller
{
    public function __construct(private readonly NodeService $nodes) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('node.view'), 403);

        return response()->json([
            'data' => Node::with('location')->withCount('servers')->get()->map(fn (Node $node) => [
                'id' => $node->id,
                'uuid' => $node->uuid,
                'name' => $node->name,
                'fqdn' => $node->fqdn,
                'location' => $node->location?->short_code,
                'servers' => $node->servers_count,
                'maintenance' => $node->is_maintenance,
                'allocated' => $node->allocatedResources(),
                'capacity' => [
                    'memory_mb' => $node->allocatableMemoryMb(),
                    'disk_mb' => $node->allocatableDiskMb(),
                    'cpu_cores' => $node->allocatableCpuCores(),
                ],
                'last_seen_at' => $node->last_seen_at,
            ]),
        ]);
    }

    public function status(Request $request, Node $node): JsonResponse
    {
        abort_unless($request->user()->can('node.view'), 403);

        try {
            return response()->json(['data' => $this->nodes->status($node)]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }
}
