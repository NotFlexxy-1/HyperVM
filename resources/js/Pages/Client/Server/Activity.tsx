import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { FileText, ScrollText } from 'lucide-react';
import ServerLayout from '@/Layouts/ServerLayout';
import { Alert, Badge, Card, Modal, Spinner, Table } from '@/Components/UI';

const when = (value?: string | number | null) => {
    if (!value) return '—';
    const date = typeof value === 'number' ? new Date(value * 1000) : new Date(value);
    return Number.isNaN(date.getTime()) ? '—' : date.toLocaleString();
};

export default function Activity({ server, permissions, tasks, proxmoxTasks, error }: any) {
    const base = `/servers/${server.uuid_short}`;
    const [log, setLog] = useState<{ upid: string; lines: string[] } | null>(null);
    const [loading, setLoading] = useState(false);

    const openLog = async (upid: string) => {
        setLoading(true);
        setLog({ upid, lines: [] });
        try {
            const response = await fetch(`${base}/activity/task-log?upid=${encodeURIComponent(upid)}`, {
                headers: { Accept: 'application/json' },
            });
            const payload = await response.json();
            const lines = Array.isArray(payload)
                ? payload.map((line: any) => (typeof line === 'string' ? line : line.t ?? ''))
                : [payload?.error ?? 'No log output was returned.'];
            setLog({ upid, lines });
        } catch {
            setLog({ upid, lines: ['The task log could not be retrieved.'] });
        } finally {
            setLoading(false);
        }
    };

    return (
        <ServerLayout server={server} permissions={permissions}>
            <div className="mt-6 space-y-6">
                {error && <Alert tone="warn">{error}</Alert>}

                <Card title="Panel activity" subtitle="Actions performed through HyperVM by you and your sub-users">
                    <Table head={['Action', 'By', 'State', 'When']} empty={tasks.data.length ? undefined : <p className="px-4 py-6 text-sm text-ink-muted">Nothing has happened on this server yet.</p>}>
                        {tasks.data.map((task: any) => (
                            <tr key={task.id}>
                                <td className="px-4 py-3 font-mono text-xs text-ink">{task.action}</td>
                                <td className="px-4 py-3 text-ink-muted">{task.user ? task.user.name : 'System'}</td>
                                <td className="px-4 py-3">
                                    <Badge tone={task.status === 'completed' ? 'ok' : task.status === 'failed' ? 'bad' : 'warn'}>
                                        {task.status ?? 'queued'}
                                    </Badge>
                                </td>
                                <td className="px-4 py-3 text-ink-muted">{when(task.created_at)}</td>
                            </tr>
                        ))}
                    </Table>

                    {tasks.links?.length > 3 && (
                        <div className="mt-4 flex flex-wrap gap-1.5">
                            {tasks.links.map((link: any, i: number) =>
                                link.url ? (
                                    <Link
                                        key={i}
                                        href={link.url}
                                        preserveScroll
                                        className={`rounded-panel px-3 py-1.5 text-xs font-medium transition ${
                                            link.active ? 'bg-brand text-brand-contrast' : 'border border-edge/60 text-ink-muted hover:text-ink'
                                        }`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ) : null,
                            )}
                        </div>
                    )}
                </Card>

                <Card title="Hypervisor tasks" subtitle="Raw task history reported by Proxmox for this VM">
                    <Table head={['Type', 'Status', 'Started', '']} empty={proxmoxTasks?.length ? undefined : <p className="px-4 py-6 text-sm text-ink-muted">No hypervisor tasks were returned.</p>}>
                        {(proxmoxTasks ?? []).map((task: any) => (
                            <tr key={task.upid}>
                                <td className="px-4 py-3 font-mono text-xs text-ink">{task.type}</td>
                                <td className="px-4 py-3">
                                    <Badge tone={task.status === 'OK' ? 'ok' : task.status ? 'bad' : 'warn'}>{task.status ?? 'running'}</Badge>
                                </td>
                                <td className="px-4 py-3 text-ink-muted">{when(task.starttime)}</td>
                                <td className="px-4 py-3 text-right">
                                    <button className="hv-btn-ghost py-1.5" onClick={() => openLog(task.upid)}>
                                        <FileText className="h-4 w-4" /> Log
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </Table>
                </Card>
            </div>

            <Modal open={Boolean(log)} onClose={() => setLog(null)} title="Task log" width="max-w-3xl">
                <div className="px-5 py-4">
                    <p className="mb-3 break-all font-mono text-[11px] text-ink-muted">{log?.upid}</p>
                    {loading ? (
                        <div className="flex items-center gap-2 text-sm text-ink-muted">
                            <Spinner /> Fetching output…
                        </div>
                    ) : (
                        <pre className="hv-scroll max-h-[55vh] overflow-auto rounded-panel border border-edge/60 bg-surface-sunken p-4 font-mono text-[11px] leading-relaxed text-ink">
                            {log?.lines.join('\n') || 'No output.'}
                        </pre>
                    )}
                    <p className="mt-3 flex items-center gap-1.5 text-xs text-ink-muted">
                        <ScrollText className="h-3.5 w-3.5" /> Output is streamed directly from the hypervisor.
                    </p>
                </div>
            </Modal>
        </ServerLayout>
    );
}
