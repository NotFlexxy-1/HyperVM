import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, Badge } from '@/Components/UI';
import { megabytes } from '@/lib/format';

export default function ServersIndex({ servers, filters }: any) {
    return (
        <AppLayout title="Servers">
            <Head title="Servers" />
            <Card title="All virtual machines" action={<Link className="hv-btn-primary py-1.5" href="/admin/servers/create">Provision server</Link>}>
                <input defaultValue={filters.search ?? ''} placeholder="Search name, UUID or VMID…" className="hv-input mb-4 max-w-sm"
                    onKeyDown={(e) => e.key === 'Enter' && router.get('/admin/servers', { search: (e.target as HTMLInputElement).value }, { preserveState: true })} />
                <table className="w-full text-left text-sm">
                    <thead className="text-xs uppercase tracking-wider text-ink-muted"><tr><th className="pb-2">Server</th><th>Owner</th><th>Node</th><th>Resources</th><th>Status</th></tr></thead>
                    <tbody className="divide-y divide-edge/50">
                        {servers.data.map((s: any) => (
                            <tr key={s.id}>
                                <td className="py-2"><Link className="font-medium text-ink hover:text-brand" href={`/admin/servers/${s.uuid_short}`}>{s.name}</Link><div className="font-mono text-xs text-ink-muted">VMID {s.vmid}</div></td>
                                <td className="text-ink-muted">{s.owner?.username}</td>
                                <td className="text-ink-muted">{s.node?.name}</td>
                                <td className="text-ink-muted">{s.cpu_cores} vCPU · {megabytes(s.memory_mb)} · {megabytes(s.disk_mb)}</td>
                                <td><Badge tone={s.status === 'ready' ? 'ok' : s.status === 'suspended' ? 'bad' : 'warn'}>{s.status}</Badge></td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </Card>
        </AppLayout>
    );
}
