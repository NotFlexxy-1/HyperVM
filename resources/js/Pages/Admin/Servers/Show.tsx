import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, Badge } from '@/Components/UI';
import { bytes, uptime } from '@/lib/format';

export default function ServerShow({ server, proxmox, tasks }: any) {
    const form = useForm({
        name: server.name, description: server.description ?? '', owner_id: server.owner_id,
        plan_id: server.plan_id ?? '', cpu_cores: server.cpu_cores, memory_mb: server.memory_mb,
        bandwidth_gb: server.bandwidth_gb ?? '', snapshot_limit: server.snapshot_limit,
        backup_limit: server.backup_limit, network_speed_mbps: server.network_speed_mbps ?? '',
    });

    return (
        <AppLayout title={server.name}>
            <Head title={server.name} />
            <div className="flex flex-wrap gap-2">
                <Link className="hv-btn-ghost" href={`/servers/${server.uuid_short}`}>Client view</Link>
                {server.status === 'suspended'
                    ? <button className="hv-btn-primary" onClick={() => router.post(`/admin/servers/${server.uuid_short}/unsuspend`)}>Unsuspend</button>
                    : <button className="hv-btn-ghost" onClick={() => router.post(`/admin/servers/${server.uuid_short}/suspend`)}>Suspend</button>}
                <button className="hv-btn-danger" onClick={() => confirm('Delete this VM from Proxmox?') && router.delete(`/admin/servers/${server.uuid_short}`, { data: { purge: true } })}>Delete</button>
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                <Card title="Proxmox">
                    {proxmox.error && <p className="text-sm text-red-400">{proxmox.error}</p>}
                    {proxmox.status && (
                        <ul className="space-y-1 text-sm text-ink-muted">
                            <li>State: <Badge tone={proxmox.status.status === 'running' ? 'ok' : 'warn'}>{proxmox.status.status}</Badge></li>
                            <li>Uptime: {uptime(proxmox.status.uptime)}</li>
                            <li>RAM: {bytes(proxmox.status.mem ?? 0)} / {bytes(proxmox.status.maxmem ?? 0)}</li>
                            <li>Disk: {bytes(proxmox.status.maxdisk ?? 0)}</li>
                        </ul>
                    )}
                </Card>

                <Card title="Configuration" className="lg:col-span-2">
                    <form className="grid gap-4 md:grid-cols-2" onSubmit={(e) => { e.preventDefault(); form.patch(`/admin/servers/${server.uuid_short}`); }}>
                        <div><label className="hv-label">Name</label><input className="hv-input" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} /></div>
                        <div><label className="hv-label">vCPU</label><input type="number" className="hv-input" value={form.data.cpu_cores} onChange={(e) => form.setData('cpu_cores', Number(e.target.value))} /></div>
                        <div><label className="hv-label">Memory (MiB)</label><input type="number" className="hv-input" value={form.data.memory_mb} onChange={(e) => form.setData('memory_mb', Number(e.target.value))} /></div>
                        <div><label className="hv-label">Backups</label><input type="number" className="hv-input" value={form.data.backup_limit} onChange={(e) => form.setData('backup_limit', Number(e.target.value))} /></div>
                        <div><label className="hv-label">Snapshots</label><input type="number" className="hv-input" value={form.data.snapshot_limit} onChange={(e) => form.setData('snapshot_limit', Number(e.target.value))} /></div>
                        <div className="md:col-span-2"><button className="hv-btn-primary" disabled={form.processing}>Apply to Proxmox</button></div>
                    </form>
                </Card>
            </div>

            <Card title="Task history">
                <ul className="divide-y divide-edge/50 text-sm">
                    {tasks.map((t: any) => (
                        <li key={t.id} className="flex justify-between py-2"><span className="text-ink">{t.action}</span><span className="font-mono text-xs text-ink-muted">{t.upid ?? t.status}</span></li>
                    ))}
                </ul>
            </Card>
        </AppLayout>
    );
}
