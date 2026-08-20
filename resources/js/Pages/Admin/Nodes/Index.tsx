import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, Badge } from '@/Components/UI';

export default function NodesIndex({ nodes }: any) {
    return (
        <AppLayout title="Nodes">
            <Head title="Nodes" />
            <Card title="Proxmox nodes" action={<Link className="hv-btn-primary py-1.5" href="/admin/nodes-new">Add node</Link>}>
                <table className="w-full text-left text-sm">
                    <thead className="text-xs uppercase tracking-wider text-ink-muted"><tr><th className="pb-2">Name</th><th>FQDN</th><th>PVE node</th><th>Servers</th><th>State</th></tr></thead>
                    <tbody className="divide-y divide-edge/50">
                        {nodes.data.map((n: any) => (
                            <tr key={n.id}>
                                <td className="py-2"><Link className="font-medium text-ink hover:text-brand" href={`/admin/nodes/${n.id}`}>{n.name}</Link></td>
                                <td className="font-mono text-xs text-ink-muted">{n.fqdn}:{n.port}</td>
                                <td className="text-ink-muted">{n.proxmox_node_name}</td>
                                <td className="text-ink-muted">{n.servers_count}</td>
                                <td><Badge tone={n.is_maintenance ? 'warn' : n.last_seen_at ? 'ok' : 'bad'}>{n.is_maintenance ? 'maintenance' : n.last_seen_at ? 'online' : 'unverified'}</Badge></td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </Card>
        </AppLayout>
    );
}
