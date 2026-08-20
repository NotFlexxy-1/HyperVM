import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, Meter, Badge } from '@/Components/UI';
import { bytes, uptime } from '@/lib/format';

export default function NodeShow({ node, allocated, limits, servers, allocations, proxmox }: any) {
    const alloc = useForm({ type: 'ipv4', address: '', range_end: '', cidr: 24, gateway: '', vlan_id: '', mac_address: '', label: '' });

    return (
        <AppLayout title={node.name}>
            <Head title={node.name} />
            <div className="flex flex-wrap gap-2">
                <Link className="hv-btn-ghost" href={`/admin/nodes/${node.id}/edit`}>Edit node</Link>
                <button className="hv-btn-primary" onClick={() => router.post(`/admin/nodes/${node.id}/test`)}>Test connection</button>
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                <Card title="Live Proxmox status">
                    {proxmox.error && <p className="text-sm text-red-400">{proxmox.error}</p>}
                    {proxmox.status && (
                        <div className="space-y-3 text-sm">
                            <p className="text-ink-muted">PVE {proxmox.version?.version} · uptime {uptime(proxmox.status.uptime)}</p>
                            <Meter label="CPU" used={Math.round((proxmox.status.cpu ?? 0) * 100)} total={100} unit="%" />
                            <p className="text-xs text-ink-muted">RAM {bytes(proxmox.status.memory?.used ?? 0)} / {bytes(proxmox.status.memory?.total ?? 0)}</p>
                        </div>
                    )}
                </Card>

                <Card title="Allocated capacity">
                    <div className="space-y-3">
                        <Meter label="Memory (MiB)" used={allocated.memory_mb} total={limits.memory_mb} />
                        <Meter label="Disk (MiB)" used={allocated.disk_mb} total={limits.disk_mb} />
                        <Meter label="vCPU" used={allocated.cpu_cores} total={limits.cpu_cores} />
                    </div>
                </Card>
            </div>

            <Card title="IP allocations">
                <form className="mb-5 grid gap-3 md:grid-cols-4" onSubmit={(e) => { e.preventDefault(); alloc.post(`/admin/nodes/${node.id}/allocations`); }}>
                    <input className="hv-input" placeholder="10.0.0.10" value={alloc.data.address} onChange={(e) => alloc.setData('address', e.target.value)} />
                    <input className="hv-input" placeholder="range end (optional)" value={alloc.data.range_end} onChange={(e) => alloc.setData('range_end', e.target.value)} />
                    <input className="hv-input" type="number" placeholder="CIDR" value={alloc.data.cidr} onChange={(e) => alloc.setData('cidr', Number(e.target.value))} />
                    <input className="hv-input" placeholder="gateway" value={alloc.data.gateway} onChange={(e) => alloc.setData('gateway', e.target.value)} />
                    <button className="hv-btn-primary md:col-span-4" disabled={alloc.processing}>Add allocations</button>
                </form>
                <ul className="grid gap-2 md:grid-cols-3">
                    {allocations.data.map((a: any) => (
                        <li key={a.id} className="flex items-center justify-between rounded-panel border border-edge/60 px-3 py-2 text-sm">
                            <span className="font-mono text-ink">{a.address}/{a.cidr}</span>
                            {a.server ? <Badge tone="ok">{a.server.name}</Badge> : (
                                <button className="text-xs text-red-400" onClick={() => router.delete(`/admin/allocations/${a.id}`)}>remove</button>
                            )}
                        </li>
                    ))}
                </ul>
            </Card>

            <Card title="Servers on this node">
                <ul className="divide-y divide-edge/50 text-sm">
                    {servers.data.map((s: any) => (
                        <li key={s.id} className="flex justify-between py-2">
                            <Link className="text-ink hover:text-brand" href={`/admin/servers/${s.uuid_short}`}>{s.name}</Link>
                            <span className="text-ink-muted">{s.owner?.username} · VMID {s.vmid}</span>
                        </li>
                    ))}
                </ul>
            </Card>
        </AppLayout>
    );
}
