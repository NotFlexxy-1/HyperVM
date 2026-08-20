import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Card } from '@/Components/UI';

export default function AuditLogsIndex({ logs, filters }: any) {
    return (
        <AppLayout title="Audit log">
            <Head title="Audit log" />
            <Card title="Recorded actions">
                <input defaultValue={filters.action ?? ''} placeholder="Filter by action…" className="hv-input mb-4 max-w-sm"
                    onKeyDown={(e) => e.key === 'Enter' && router.get('/admin/audit-logs', { action: (e.target as HTMLInputElement).value }, { preserveState: true })} />
                <table className="w-full text-left text-sm">
                    <thead className="text-xs uppercase tracking-wider text-ink-muted"><tr><th className="pb-2">When</th><th>Actor</th><th>Action</th><th>IP</th></tr></thead>
                    <tbody className="divide-y divide-edge/50">
                        {logs.data.map((l: any) => (
                            <tr key={l.id}>
                                <td className="py-2 text-xs text-ink-muted">{new Date(l.created_at).toLocaleString()}</td>
                                <td className="text-ink">{l.user?.username ?? 'system'}</td>
                                <td className="font-mono text-xs text-ink">{l.action}</td>
                                <td className="font-mono text-xs text-ink-muted">{l.ip_address}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </Card>
        </AppLayout>
    );
}
