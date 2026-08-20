<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Node;
use App\Models\Server;
use App\Models\User;
use App\Services\Proxmox\NodeService;
use App\Services\SettingsRepository;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly NodeService $nodes,
        private readonly SettingsRepository $settings,
    ) {}

    public function __invoke(): Response
    {
        $nodes = Node::with('location')->get();

        return Inertia::render('Admin/Dashboard', [
            'widgets' => $this->settings->layoutWidgets(),
            'stats' => [
                'servers' => Server::count(),
                'servers_ready' => Server::where('status', Server::STATUS_READY)->count(),
                'servers_suspended' => Server::where('status', Server::STATUS_SUSPENDED)->count(),
                'nodes' => $nodes->count(),
                'nodes_online' => $nodes->where('last_seen_at', '>=', now()->subMinutes(5))->count(),
                'users' => User::count(),
                'users_suspended' => User::where('is_suspended', true)->count(),
            ],
            'capacity' => $nodes->map(function (Node $node) {
                $used = $node->allocatedResources();

                return [
                    'id' => $node->id,
                    'name' => $node->name,
                    'location' => $node->location?->short_code,
                    'maintenance' => $node->is_maintenance,
                    'last_seen_at' => $node->last_seen_at,
                    'memory' => ['used' => $used['memory_mb'], 'total' => $node->allocatableMemoryMb()],
                    'disk' => ['used' => $used['disk_mb'], 'total' => $node->allocatableDiskMb()],
                    'cpu' => ['used' => $used['cpu_cores'], 'total' => $node->allocatableCpuCores()],
                    'servers' => $used['servers'],
                ];
            })->values(),
            'recentServers' => Server::with(['owner:id,name,username', 'node:id,name'])
                ->latest()->limit(8)->get(),
            'recentActivity' => AuditLog::with('user:id,name,username')
                ->latest()->limit(12)->get(),
        ]);
    }

    /** Live Proxmox status for one node; polled by the dashboard. */
    public function nodeStatus(Node $node): array
    {
        return $this->nodes->status($node);
    }
}
