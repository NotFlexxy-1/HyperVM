<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Node;
use App\Services\AuditLogger;
use App\Services\Proxmox\NodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class NodeController extends Controller
{
    public function __construct(
        private readonly NodeService $nodes,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): Response
    {
        $nodes = Node::query()
            ->with('location')
            ->withCount('servers')
            ->when($request->string('search')->toString(), fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")->orWhere('fqdn', 'like', "%{$s}%");
            }))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Nodes/Index', [
            'nodes' => $nodes,
            'filters' => ['search' => $request->query('search')],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Nodes/Create', [
            'locations' => Location::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $node = Node::create($data);
        $this->audit->log('admin.node.created', $node, ['name' => $node->name]);

        return redirect()->route('admin.nodes.show', $node)->with('success', 'Node created.');
    }

    public function show(Node $node): Response
    {
        $status = null;
        $version = null;
        $error = null;

        try {
            $status = $this->nodes->status($node);
            $version = $this->nodes->version($node);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return Inertia::render('Admin/Nodes/Show', [
            'node' => $node->load('location'),
            'allocated' => $node->allocatedResources(),
            'limits' => [
                'memory_mb' => $node->allocatableMemoryMb(),
                'disk_mb' => $node->allocatableDiskMb(),
                'cpu_cores' => $node->allocatableCpuCores(),
            ],
            'servers' => $node->servers()->with('owner:id,name,username')->paginate(15),
            'allocations' => $node->allocations()->with('server:id,name,uuid_short')->paginate(25, ['*'], 'allocationPage'),
            'metrics' => $node->metrics()->latest('recorded_at')->limit(60)->get()->reverse()->values(),
            'proxmox' => ['status' => $status, 'version' => $version, 'error' => $error],
        ]);
    }

    public function edit(Node $node): Response
    {
        return Inertia::render('Admin/Nodes/Edit', [
            'node' => $node,
            'locations' => Location::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Node $node): RedirectResponse
    {
        $data = $this->validated($request, $node);

        if (empty($data['token_secret'])) {
            unset($data['token_secret']);
        }

        $node->update($data);
        $this->audit->log('admin.node.updated', $node);

        return back()->with('success', 'Node updated.');
    }

    public function destroy(Node $node): RedirectResponse
    {
        if ($node->servers()->exists()) {
            return back()->with('error', 'Delete or migrate the servers on this node first.');
        }

        $this->audit->log('admin.node.deleted', $node, ['name' => $node->name]);
        $node->delete();

        return redirect()->route('admin.nodes.index')->with('success', 'Node deleted.');
    }

    /** Verify credentials against the live Proxmox API. */
    public function test(Node $node): RedirectResponse
    {
        try {
            $version = $this->nodes->version($node);
            $this->nodes->recordMetrics($node);

            return back()->with('success', 'Connected to Proxmox VE '.($version['version'] ?? 'unknown').'.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    private function validated(Request $request, ?Node $node = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'fqdn' => ['required', 'string', 'max:190'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'cluster' => ['nullable', 'string', 'max:120'],
            'proxmox_node_name' => ['required', 'string', 'max:120'],
            'token_id' => ['required', 'string', 'max:190'],
            'token_secret' => [$node ? 'nullable' : 'required', 'string', 'max:255'],
            'verify_tls' => ['boolean'],
            'storage_pool' => ['required', 'string', 'max:120'],
            'backup_storage_pool' => ['nullable', 'string', 'max:120'],
            'iso_storage_pool' => ['nullable', 'string', 'max:120'],
            'network_bridge' => ['required', 'string', 'max:60'],
            'memory_mb' => ['required', 'integer', 'min:512'],
            'memory_overallocate' => ['required', 'integer', 'between:0,500'],
            'disk_mb' => ['required', 'integer', 'min:1024'],
            'disk_overallocate' => ['required', 'integer', 'between:0,500'],
            'cpu_cores' => ['required', 'integer', 'min:1'],
            'cpu_overallocate' => ['required', 'integer', 'between:0,500'],
            'vm_limit' => ['nullable', 'integer', 'min:1'],
            'is_maintenance' => ['boolean'],
            'is_deployable' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ] + ($node ? [] : ['fqdn' => ['required', 'string', 'max:190', Rule::unique('nodes', 'fqdn')]]));
    }
}
