<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $servers = $user->accessibleServers()
            ->with(['node:id,name,location_id', 'node.location:id,short_code', 'allocations'])
            ->when($request->string('search')->toString(), fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Client/Dashboard', [
            'servers' => $servers,
            'filters' => $request->only('search'),
            'totals' => [
                'servers' => $user->accessibleServers()->count(),
                'cpu_cores' => (int) $user->accessibleServers()->sum('cpu_cores'),
                'memory_mb' => (int) $user->accessibleServers()->sum('memory_mb'),
                'disk_mb' => (int) $user->accessibleServers()->sum('disk_mb'),
            ],
        ]);
    }
}
