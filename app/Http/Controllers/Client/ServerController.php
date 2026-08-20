<?php

namespace App\Http\Controllers\Client;

use App\Exceptions\ProxmoxRequestException;
use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Services\AuditLogger;
use App\Services\Proxmox\BackupService;
use App\Services\Proxmox\ServerConfigService;
use App\Services\Proxmox\ServerService;
use App\Support\ServerPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ServerController extends Controller
{
    public function __construct(
        private readonly ServerService $servers,
        private readonly ServerConfigService $configs,
        private readonly BackupService $backups,
        private readonly AuditLogger $audit,
    ) {}

    /** Abort unless the current user holds the given per-server permission. */
    private function authorizeServer(Request $request, Server $server, string $permission): void
    {
        abort_unless(
            ServerPermissions::allows($request->user(), $server, $permission),
            403,
            'You do not have permission to perform this action on this server.',
        );
    }

    /** Props shared by every server page so the shell can render consistently. */
    private function shell(Request $request, Server $server): array
    {
        $server->loadMissing([
            'node:id,name,fqdn,port,location_id,proxmox_node_name',
            'node.location:id,name,short_code',
            'allocations',
            'plan:id,name,cpu_cores,memory_mb,disk_mb,bandwidth_gb',
        ]);

        return [
            'server' => $server,
            'permissions' => ServerPermissions::for($request->user(), $server),
        ];
    }

    public function show(Request $request, Server $server): Response
    {
        $status = null;
        $error = null;

        try {
            $status = $this->servers->status($server);
        } catch (ProxmoxRequestException $e) {
            $error = $e->getMessage();
        }

        return Inertia::render('Client/Server/Overview', array_merge($this->shell($request, $server), [
            'status' => $status,
            'error' => $error,
            'recentTasks' => $server->tasks()->with('user:id,name,username')->latest()->limit(6)->get(),
        ]));
    }

    /** Polled by the frontend for live CPU/RAM/uptime (cached server-side). */
    public function status(Server $server): JsonResponse
    {
        try {
            return response()->json($this->servers->status($server));
        } catch (ProxmoxRequestException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }

    public function metrics(Request $request, Server $server): JsonResponse
    {
        $timeframe = $request->string('timeframe')->whenEmpty(fn () => 'hour')->toString();

        abort_unless(in_array($timeframe, ['hour', 'day', 'week', 'month', 'year'], true), 422);

        try {
            return response()->json($this->servers->metrics($server, $timeframe));
        } catch (ProxmoxRequestException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }

    public function power(Request $request, Server $server): RedirectResponse
    {
        $action = $request->validate([
            'action' => ['required', 'in:start,stop,shutdown,reboot,reset'],
        ])['action'];

        $permission = match ($action) {
            'start' => 'control.start',
            'stop', 'shutdown' => 'control.stop',
            default => 'control.restart',
        };

        $this->authorizeServer($request, $server, $permission);

        abort_unless($server->canBeControlled(), 403, 'This server is locked or suspended.');

        try {
            $upid = $this->servers->power($server, $action);
            $this->servers->recordTask($server, "power.{$action}", $upid, $request->user()->id);
        } catch (ProxmoxRequestException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->audit->log("server.power.{$action}", $server);

        return back()->with('success', "Power action '{$action}' sent.");
    }

    public function console(Request $request, Server $server): Response
    {
        $this->authorizeServer($request, $server, 'control.console');
        abort_unless($server->canBeControlled(), 403, 'This server is locked or suspended.');

        try {
            $ticket = $this->servers->consoleTicket($server);
        } catch (ProxmoxRequestException $e) {
            return Inertia::render('Client/Server/Console', array_merge($this->shell($request, $server), [
                'ticket' => null,
                'error' => $e->getMessage(),
            ]));
        }

        $this->audit->log('server.console.opened', $server);

        return Inertia::render('Client/Server/Console', array_merge($this->shell($request, $server), [
            'ticket' => $ticket,
            'error' => null,
        ]));
    }

    /** Issues a fresh VNC ticket without a full page reload. */
    public function consoleTicket(Request $request, Server $server): JsonResponse
    {
        $this->authorizeServer($request, $server, 'control.console');

        try {
            return response()->json($this->servers->consoleTicket($server));
        } catch (ProxmoxRequestException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Resources / hardware                                                */
    /* ------------------------------------------------------------------ */

    public function resources(Request $request, Server $server): Response
    {
        $config = [];
        $error = null;

        try {
            $config = $this->configs->config($server);
        } catch (ProxmoxRequestException $e) {
            $error = $e->getMessage();
        }

        return Inertia::render('Client/Server/Resources', array_merge($this->shell($request, $server), [
            'config' => $config,
            'error' => $error,
            'templates' => config('hypervm.proxmox.supported_templates'),
        ]));
    }

    public function updateHardware(Request $request, Server $server): RedirectResponse
    {
        $this->authorizeServer($request, $server, 'settings.hardware');

        $data = $request->validate([
            'cpu_cores' => ['required', 'integer', 'min:1', 'max:128'],
            'memory_mb' => ['required', 'integer', 'min:256'],
            'disk_mb' => ['required', 'integer', 'min:1024'],
        ]);

        $plan = $server->plan;

        if ($plan) {
            abort_if(
                $data['cpu_cores'] > $plan->cpu_cores || $data['memory_mb'] > $plan->memory_mb || $data['disk_mb'] > $plan->disk_mb,
                422,
                'The requested resources exceed the limits of your plan.',
            );
        }

        try {
            $this->configs->applyHardware($server, $data['cpu_cores'], $data['memory_mb'], $data['disk_mb']);
        } catch (ProxmoxRequestException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->audit->log('server.hardware.updated', $server, $data);

        return back()->with('success', 'Hardware updated. Restart the server for CPU and memory changes to take effect.');
    }

    public function updateCloudInit(Request $request, Server $server): RedirectResponse
    {
        $this->authorizeServer($request, $server, 'settings.cloudinit');

        $data = $request->validate([
            'ci_user' => ['nullable', 'string', 'max:60', 'regex:/^[a-z_][a-z0-9_-]*$/'],
            'root_password' => ['nullable', 'string', 'min:8', 'max:128'],
            'ssh_keys' => ['nullable', 'string', 'max:8000'],
            'nameserver' => ['nullable', 'string', 'max:190'],
            'searchdomain' => ['nullable', 'string', 'max:190'],
        ]);

        try {
            $this->configs->updateCloudInit($server, $data);
        } catch (ProxmoxRequestException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->audit->log('server.cloudinit.updated', $server);

        return back()->with('success', 'Cloud-init configuration written. Reboot to apply it inside the guest.');
    }

    /* ------------------------------------------------------------------ */
    /* Networking                                                          */
    /* ------------------------------------------------------------------ */

    public function network(Request $request, Server $server): Response
    {
        $this->authorizeServer($request, $server, 'network.read');

        $config = [];
        $firewall = [];
        $rules = [];
        $guest = [];
        $error = null;

        try {
            $config = $this->configs->config($server);
            $firewall = $this->configs->firewallOptions($server);
            $rules = $this->configs->firewallRules($server);
        } catch (ProxmoxRequestException $e) {
            $error = $e->getMessage();
        }

        try {
            $guest = $this->configs->guestNetworkInterfaces($server);
        } catch (ProxmoxRequestException) {
            // The guest agent is optional; absence is not an error.
            $guest = [];
        }

        return Inertia::render('Client/Server/Network', array_merge($this->shell($request, $server), [
            'config' => $config,
            'firewall' => $firewall,
            'rules' => $rules,
            'guestInterfaces' => $guest,
            'error' => $error,
        ]));
    }

    public function syncNetwork(Request $request, Server $server): RedirectResponse
    {
        $this->authorizeServer($request, $server, 'network.update');

        try {
            $this->configs->syncNetworkFromAllocations($server);
        } catch (ProxmoxRequestException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->audit->log('server.network.synced', $server);

        return back()->with('success', 'Network configuration written from your IP allocations.');
    }

    public function updateFirewall(Request $request, Server $server): RedirectResponse
    {
        $this->authorizeServer($request, $server, 'network.update');

        $data = $request->validate([
            'enable' => ['required', 'boolean'],
            'policy_in' => ['required', 'in:ACCEPT,DROP,REJECT'],
            'policy_out' => ['required', 'in:ACCEPT,DROP,REJECT'],
        ]);

        try {
            $this->configs->setFirewallOptions($server, [
                'enable' => $data['enable'] ? 1 : 0,
                'policy_in' => $data['policy_in'],
                'policy_out' => $data['policy_out'],
            ]);
        } catch (ProxmoxRequestException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->audit->log('server.firewall.updated', $server, $data);

        return back()->with('success', 'Firewall settings saved.');
    }

    public function storeFirewallRule(Request $request, Server $server): RedirectResponse
    {
        $this->authorizeServer($request, $server, 'network.update');

        $data = $request->validate([
            'type' => ['required', 'in:in,out'],
            'action' => ['required', 'in:ACCEPT,DROP,REJECT'],
            'proto' => ['nullable', 'in:tcp,udp,icmp'],
            'dport' => ['nullable', 'string', 'max:60'],
            'sport' => ['nullable', 'string', 'max:60'],
            'source' => ['nullable', 'string', 'max:120'],
            'dest' => ['nullable', 'string', 'max:120'],
            'comment' => ['nullable', 'string', 'max:190'],
        ]);

        try {
            $this->configs->createFirewallRule($server, array_filter($data, fn ($v) => $v !== null && $v !== ''));
        } catch (ProxmoxRequestException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Firewall rule created.');
    }

    public function destroyFirewallRule(Request $request, Server $server, int $position): RedirectResponse
    {
        $this->authorizeServer($request, $server, 'network.update');

        try {
            $this->configs->deleteFirewallRule($server, $position);
        } catch (ProxmoxRequestException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Firewall rule deleted.');
    }

    public function updateNetworkRate(Request $request, Server $server): RedirectResponse
    {
        $this->authorizeServer($request, $server, 'network.update');

        $data = $request->validate([
            'network_speed_mbps' => ['nullable', 'integer', 'min:1', 'max:40000'],
        ]);

        $plan = $server->plan;

        abort_if(
            $plan?->network_speed_mbps && ($data['network_speed_mbps'] ?? 0) > $plan->network_speed_mbps,
            422,
            'The requested link speed exceeds your plan.',
        );

        try {
            $this->configs->setNetworkRate($server, $data['network_speed_mbps'] ?? null);
        } catch (ProxmoxRequestException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Link speed updated.');
    }

    /* ------------------------------------------------------------------ */
    /* Media (ISO) — the panel has no filesystem access into the guest, so  */
    /* installation media is what can genuinely be managed from here.       */
    /* ------------------------------------------------------------------ */

    public function media(Request $request, Server $server): Response
    {
        $this->authorizeServer($request, $server, 'media.manage');

        $images = [];
        $config = [];
        $error = null;

        try {
            $images = $this->configs->isoImages($server);
            $config = $this->configs->config($server);
        } catch (ProxmoxRequestException $e) {
            $error = $e->getMessage();
        }

        return Inertia::render('Client/Server/Media', array_merge($this->shell($request, $server), [
            'images' => $images,
            'config' => $config,
            'error' => $error,
        ]));
    }

    public function mountMedia(Request $request, Server $server): RedirectResponse
    {
        $this->authorizeServer($request, $server, 'media.manage');

        $data = $request->validate(['volid' => ['required', 'string', 'max:255']]);

        try {
            $this->configs->mountIso($server, $data['volid']);
        } catch (ProxmoxRequestException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->audit->log('server.media.mounted', $server, $data);

        return back()->with('success', 'ISO mounted to the virtual CD-ROM drive.');
    }

    public function unmountMedia(Request $request, Server $server): RedirectResponse
    {
        $this->authorizeServer($request, $server, 'media.manage');

        try {
            $this->configs->unmountIso($server);
        } catch (ProxmoxRequestException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'ISO unmounted.');
    }

    public function updateBootOrder(Request $request, Server $server): RedirectResponse
    {
        $this->authorizeServer($request, $server, 'media.manage');

        $data = $request->validate([
            'order' => ['required', 'string', 'regex:/^[a-z0-9;]+$/', 'max:60'],
        ]);

        try {
            $this->configs->setBootOrder($server, $data['order']);
        } catch (ProxmoxRequestException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Boot order updated.');
    }

    /* ------------------------------------------------------------------ */
    /* Backups & snapshots                                                 */
    /* ------------------------------------------------------------------ */

    public function backups(Request $request, Server $server): Response
    {
        $this->authorizeServer($request, $server, 'backup.read');

        return Inertia::render('Client/Server/Backups', array_merge($this->shell($request, $server), [
            'backups' => $server->backups()->latest()->get(),
            'snapshots' => $server->snapshots()->latest()->get(),
        ]));
    }

    public function createBackup(Request $request, Server $server): RedirectResponse
    {
        $this->authorizeServer($request, $server, 'backup.create');

        $data = $request->validate(['name' => ['nullable', 'string', 'max:120']]);

        try {
            $backup = $this->backups->createBackup($server, $data['name'] ?? null);
        } catch (ProxmoxRequestException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->audit->log('server.backup.created', $server, ['backup' => $backup->uuid]);

        return back()->with('success', 'Backup created.');
    }

    public function restoreBackup(Request $request, Server $server, string $backupUuid): RedirectResponse
    {
        $this->authorizeServer($request, $server, 'backup.restore');

        $backup = $server->backups()->where('uuid', $backupUuid)->firstOrFail();

        try {
            $this->backups->restoreBackup($server, $backup);
        } catch (ProxmoxRequestException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->audit->log('server.backup.restored', $server, ['backup' => $backup->uuid]);

        return back()->with('success', 'Backup restored.');
    }

    public function deleteBackup(Request $request, Server $server, string $backupUuid): RedirectResponse
    {
        $this->authorizeServer($request, $server, 'backup.delete');

        $backup = $server->backups()->where('uuid', $backupUuid)->firstOrFail();

        try {
            $this->backups->deleteBackup($backup);
        } catch (ProxmoxRequestException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Backup deleted.');
    }

    public function createSnapshot(Request $request, Server $server): RedirectResponse
    {
        $this->authorizeServer($request, $server, 'snapshot.create');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:40', 'regex:/^[a-zA-Z][a-zA-Z0-9_\-]*$/'],
            'description' => ['nullable', 'string', 'max:255'],
            'include_ram' => ['boolean'],
        ]);

        try {
            $this->backups->createSnapshot($server, $data['name'], $data['description'] ?? null, (bool) ($data['include_ram'] ?? false));
        } catch (ProxmoxRequestException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Snapshot created.');
    }

    public function rollbackSnapshot(Request $request, Server $server, int $snapshot): RedirectResponse
    {
        $this->authorizeServer($request, $server, 'snapshot.rollback');

        $model = $server->snapshots()->findOrFail($snapshot);

        try {
            $this->backups->rollbackSnapshot($server, $model);
        } catch (ProxmoxRequestException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->audit->log('server.snapshot.rollback', $server, ['snapshot' => $model->name]);

        return back()->with('success', 'Snapshot restored.');
    }

    public function deleteSnapshot(Request $request, Server $server, int $snapshot): RedirectResponse
    {
        $this->authorizeServer($request, $server, 'snapshot.delete');

        $model = $server->snapshots()->findOrFail($snapshot);

        try {
            $this->backups->deleteSnapshot($server, $model);
        } catch (ProxmoxRequestException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Snapshot deleted.');
    }

    /* ------------------------------------------------------------------ */
    /* Activity                                                            */
    /* ------------------------------------------------------------------ */

    public function activity(Request $request, Server $server): Response
    {
        $this->authorizeServer($request, $server, 'activity.read');

        $proxmoxTasks = [];
        $error = null;

        try {
            $proxmoxTasks = $this->configs->proxmoxTasks($server);
        } catch (ProxmoxRequestException $e) {
            $error = $e->getMessage();
        }

        return Inertia::render('Client/Server/Activity', array_merge($this->shell($request, $server), [
            'tasks' => $server->tasks()->with('user:id,name,username')->latest()->paginate(20),
            'proxmoxTasks' => $proxmoxTasks,
            'error' => $error,
        ]));
    }

    public function taskLog(Request $request, Server $server): JsonResponse
    {
        $this->authorizeServer($request, $server, 'activity.read');

        $upid = $request->string('upid')->toString();

        abort_if($upid === '', 422);

        try {
            return response()->json($this->configs->taskLog($server, $upid));
        } catch (ProxmoxRequestException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Settings                                                            */
    /* ------------------------------------------------------------------ */

    public function settings(Request $request, Server $server): Response
    {
        return Inertia::render('Client/Server/Settings', array_merge($this->shell($request, $server), [
            'subusers' => $server->subusers()->get(['users.id', 'users.name', 'users.username', 'users.email']),
            'subuserPermissions' => ServerPermissions::grouped(),
            'templates' => config('hypervm.proxmox.supported_templates'),
        ]));
    }

    public function rename(Request $request, Server $server): RedirectResponse
    {
        $this->authorizeServer($request, $server, 'settings.rename');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $server->update($data);

        $this->audit->log('server.renamed', $server, $data);

        return back()->with('success', 'Server details updated.');
    }

    public function reinstall(Request $request, Server $server): RedirectResponse
    {
        $this->authorizeServer($request, $server, 'settings.reinstall');

        $data = $request->validate([
            'template' => ['required', 'string', Rule::in(array_keys(config('hypervm.proxmox.supported_templates')))],
            'root_password' => ['nullable', 'string', 'min:8', 'max:128'],
            'ssh_keys' => ['nullable', 'string', 'max:8000'],
            'confirm' => ['accepted'],
        ]);

        try {
            $this->configs->reinstall($server, $data['template'], $data['root_password'] ?? null, $data['ssh_keys'] ?? null);
        } catch (ProxmoxRequestException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->audit->log('server.reinstalled', $server, ['template' => $data['template']]);

        return back()->with('success', 'The server was reinstalled from the selected template.');
    }
}
