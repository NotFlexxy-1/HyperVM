import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, Meter, Stat, Badge } from '@/Components/UI';
import { megabytes } from '@/lib/format';

const span = (n: number) => `col-span-12 lg:col-span-${n}`;

export default function AdminDashboard({ stats, capacity, recentServers, recentActivity, widgets }: any) {
    const enabled = (key: string) => widgets.find((w: any) => w.key === key && w.enabled);
    const width = (key: string) => enabled(key)?.span ?? 12;

    return (
        <AppLayout title="Control centre">
            <Head title="Admin overview" />
            <div className="grid grid-cols-12 gap-6">
                {enabled('resource-summary') && (
                    <div className={`${span(width('resource-summary'))} grid gap-4 sm:grid-cols-2 xl:grid-cols-4`}>
                        <Stat label="Servers" value={stats.servers} hint={`${stats.servers_ready} ready · ${stats.servers_suspended} suspended`} />
                        <Stat label="Nodes" value={stats.nodes} hint={`${stats.nodes_online} reporting in the last 5 min`} />
                        <Stat label="Users" value={stats.users} hint={`${stats.users_suspended} suspended`} />
                        <Stat label="Clusters" value={new Set(capacity.map((c: any) => c.location ?? '—')).size} hint="Distinct locations" />
                    </div>
                )}

                {enabled('node-health') && (
                    <div className={span(width('node-health'))}>
                        <Card title="Node capacity">
                            <div className="space-y-5">
                                {capacity.map((node: any) => (
                                    <div key={node.id} className="space-y-2">
                                        <div className="flex items-center justify-between">
                                            <Link href={`/admin/nodes/${node.id}`} className="text-sm font-semibold text-ink hover:text-brand">
                                                {node.name} <span className="text-ink-muted">{node.location ?? ''}</span>
                                            </Link>
                                            <Badge tone={node.maintenance ? 'warn' : node.last_seen_at ? 'ok' : 'bad'}>
                                                {node.maintenance ? 'maintenance' : node.last_seen_at ? 'online' : 'never seen'}
                                            </Badge>
                                        </div>
                                        <Meter label="Memory" used={Math.round(node.memory.used / 1024)} total={Math.round(node.memory.total / 1024)} unit=" GiB" />
                                        <Meter label="Disk" used={Math.round(node.disk.used / 1024)} total={Math.round(node.disk.total / 1024)} unit=" GiB" />
                                        <Meter label="vCPU" used={node.cpu.used} total={node.cpu.total} />
                                    </div>
                                ))}
                                {capacity.length === 0 && (
                                    <p className="text-sm text-ink-muted">No nodes yet — <Link className="text-brand" href="/admin/nodes/create">connect a Proxmox node</Link>.</p>
                                )}
                            </div>
                        </Card>
                    </div>
                )}

                {enabled('recent-activity') && (
                    <div className={span(width('recent-activity'))}>
                        <Card title="Recent activity">
                            <ul className="space-y-3 text-sm">
                                {recentActivity.map((log: any) => (
                                    <li key={log.id} className="flex justify-between gap-3 border-b border-edge/50 pb-2 last:border-0">
                                        <span className="truncate text-ink">{log.action}</span>
                                        <span className="shrink-0 text-xs text-ink-muted">{log.user?.username ?? 'system'}</span>
                                    </li>
                                ))}
                                {recentActivity.length === 0 && <li className="text-ink-muted">Nothing logged yet.</li>}
                            </ul>
                        </Card>
                    </div>
                )}

                {enabled('server-list') && (
                    <div className={span(width('server-list'))}>
                        <Card title="Newest servers" action={<Link className="hv-btn-primary py-1.5" href="/admin/servers/create">Provision</Link>}>
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="text-xs uppercase tracking-wider text-ink-muted">
                                        <tr><th className="pb-2">Name</th><th>Owner</th><th>Node</th><th>Resources</th><th>Status</th></tr>
                                    </thead>
                                    <tbody className="divide-y divide-edge/50">
                                        {recentServers.map((s: any) => (
                                            <tr key={s.id}>
                                                <td className="py-2"><Link className="font-medium text-ink hover:text-brand" href={`/admin/servers/${s.uuid_short}`}>{s.name}</Link></td>
                                                <td className="text-ink-muted">{s.owner?.username}</td>
                                                <td className="text-ink-muted">{s.node?.name}</td>
                                                <td className="text-ink-muted">{s.cpu_cores} vCPU · {megabytes(s.memory_mb)}</td>
                                                <td><Badge tone={s.status === 'ready' ? 'ok' : 'warn'}>{s.status}</Badge></td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </Card>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
