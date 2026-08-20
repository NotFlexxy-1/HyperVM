<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ProxmoxRequestException;
use App\Http\Controllers\Controller;
use App\Models\Allocation;
use App\Models\Node;
use App\Models\Plan;
use App\Models\Server;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Proxmox\ServerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServerController extends Controller
{
    public function __construct(
        private readonly ServerService $servers,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): Response
    {
        $servers = Server::query()
            ->with(['owner:id,name,username', 'node:id,name', 'plan:id,name'])
            ->when($request->string('search')->toString(), fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('uuid_short', 'like', "%{$s}%")
                    ->orWhere('vmid', $s);
            }))
            ->when($request->query('node'), fn ($q, $n) => $q->where('node_id', $n))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/Servers/Index', [
            'servers' => $servers,
            'nodes' => Node::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only('search', 'node', 'status'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Servers/Create', [
            'nodes' => Node::where('is_deployable', true)->orderBy('name')->get(),
            'plans' => Plan::orderBy('sort_order')->get(),
            'templates' => config('hypervm.proxmox.supported_templates'),
        ]);
    }

    public function availableAllocations(Node $node)
    {
        return Allocation::where('node_id', $node->id)->available()->orderBy('address')->get();
    }

    public function searchUsers(Request $request)
    {
        $term = $request->string('query')->toString();

        return User::query()
            ->when($term, fn ($q) => $q->where('email', 'like', "%{$term}%")
                ->orWhere('username', 'like', "%{$term}%")
                ->orWhere('name', 'like', "%{$term}%"))
            ->limit(15)
            ->get(['id', 'name', 'username', 'email']);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'owner_id' => ['required', 'exists:users,id'],
            'node_id' => ['required', 'exists:nodes,id'],
            'plan_id' => ['nullable', 'exists:plans,id'],
            'cpu_cores' => ['required_without:plan_id', 'nullable', 'integer', 'min:1', 'max:256'],
            'memory_mb' => ['required_without:plan_id', 'nullable', 'integer', 'min:256'],
            'disk_mb' => ['required_without:plan_id', 'nullable', 'integer', 'min:1024'],
            'bandwidth_gb' => ['nullable', 'integer', 'min:0'],
            'network_speed_mbps' => ['nullable', 'integer', 'min:1'],
            'template' => ['nullable', 'string', 'max:60'],
            'allocation_ids' => ['array'],
            'allocation_ids.*' => ['integer', 'exists:allocations,id'],
            'root_password' => ['nullable', 'string', 'min:8', 'max:128'],
            'ssh_keys' => ['nullable', 'string', 'max:8000'],
            'start_after_install' => ['boolean'],
        ]);

        try {
            $server = $this->servers->create($data + ['actor_id' => $request->user()->id]);
        } catch (ProxmoxRequestException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        $this->audit->log('admin.server.created', $server, ['vmid' => $server->vmid]);

        return redirect()->route('admin.servers.show', $server)->with('success', 'Server provisioned.');
    }

    public function show(Server $server): Response
    {
        $status = null;
        $error = null;

        try {
            $status = $this->servers->status($server);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return Inertia::render('Admin/Servers/Show', [
            'server' => $server->load(['owner', 'node', 'plan', 'allocations', 'subusers']),
            'proxmox' => ['status' => $status, 'error' => $error],
            'tasks' => $server->tasks()->with('user:id,username')->latest()->limit(25)->get(),
            'availableAllocations' => Allocation::where('node_id', $server->node_id)->available()->get(),
            'plans' => Plan::orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Server $server): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'owner_id' => ['required', 'exists:users,id'],
            'plan_id' => ['nullable', 'exists:plans,id'],
            'cpu_cores' => ['required', 'integer', 'min:1', 'max:256'],
            'memory_mb' => ['required', 'integer', 'min:256'],
            'bandwidth_gb' => ['nullable', 'integer', 'min:0'],
            'snapshot_limit' => ['required', 'integer', 'min:0'],
            'backup_limit' => ['required', 'integer', 'min:0'],
            'network_speed_mbps' => ['nullable', 'integer', 'min:1'],
        ]);

        $server->update($data);

        try {
            $this->servers->updateConfiguration($server, [
                'cores' => $server->cpu_cores,
                'memory' => $server->memory_mb,
            ]);
        } catch (ProxmoxRequestException $e) {
            return back()->with('error', 'Database updated, but Proxmox rejected the change: '.$e->getMessage());
        }

        $this->audit->log('admin.server.updated', $server);

        return back()->with('success', 'Server updated.');
    }

    public function resize(Request $request, Server $server): RedirectResponse
    {
        $data = $request->validate([
            'additional_gb' => ['required', 'integer', 'min:1', 'max:4096'],
            'disk' => ['required', 'string', 'max:20'],
        ]);

        $this->servers->resize($server, $data['additional_gb'], $data['disk']);
        $this->audit->log('admin.server.resized', $server, $data);

        return back()->with('success', 'Disk expanded.');
    }

    public function suspend(Server $server): RedirectResponse
    {
        $this->servers->suspend($server);
        $this->audit->log('admin.server.suspended', $server);

        return back()->with('success', 'Server suspended.');
    }

    public function unsuspend(Server $server): RedirectResponse
    {
        $this->servers->unsuspend($server);
        $this->audit->log('admin.server.unsuspended', $server);

        return back()->with('success', 'Server unsuspended.');
    }

    public function destroy(Request $request, Server $server): RedirectResponse
    {
        $purge = $request->boolean('purge', true);
        $this->audit->log('admin.server.deleted', $server, ['vmid' => $server->vmid, 'purge' => $purge]);
        $this->servers->destroy($server, $purge);

        return redirect()->route('admin.servers.index')->with('success', 'Server deleted.');
    }
}
