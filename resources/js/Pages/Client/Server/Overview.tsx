import { useEffect, useState } from 'react';
import axios from 'axios';
import { Cpu, Gauge, HardDrive, MemoryStick, Network as NetworkIcon, Timer } from 'lucide-react';
import ServerLayout from '@/Layouts/ServerLayout';
import { Alert, Card, EmptyState, KeyValue, Meter, Skeleton, Stat } from '@/Components/UI';
import { bytes, megabytes, percent, uptime } from '@/lib/format';

interface Status {
    status?: string;
    cpu?: number;
    cpus?: number;
    mem?: number;
    maxmem?: number;
    disk?: number;
    maxdisk?: number;
    uptime?: number;
    netin?: number;
    netout?: number;
    diskread?: number;
    diskwrite?: number;
    qmpstatus?: string;
    ha?: unknown;
}

export default function Overview({ server, permissions, status, error, recentTasks }: any) {
    const [live, setLive] = useState<Status | null>(status ?? null);
    const [pollError, setPollError] = useState<string | null>(null);
    const [loading, setLoading] = useState(status === null && !error);

    useEffect(() => {
        let cancelled = false;
        const tick = async () => {
            try {
                const { data } = await axios.get(`/servers/${server.uuid_short}/status`);
                if (!cancelled) {
                    setLive(data);
                    setPollError(null);
                    setLoading(false);
                }
            } catch (e: any) {
                if (!cancelled) {
                    setPollError(e?.response?.data?.error ?? 'Unable to reach the hypervisor.');
                    setLoading(false);
                }
            }
        };
        tick();
        const id = window.setInterval(tick, 5000);
        return () => {
            cancelled = true;
            window.clearInterval(id);
        };
    }, [server.uuid_short]);

    const state = live?.status ?? 'unknown';
    const cpuPct = Math.round((live?.cpu ?? 0) * 100);

    return (
        <ServerLayout server={server} permissions={permissions} state={state}>
            <div className="mt-6 space-y-6">
                {(error || pollError) && <Alert tone="error">{error ?? pollError}</Alert>}

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {loading ? (
                        Array.from({ length: 4 }).map((_, i) => <Skeleton key={i} className="h-28" />)
                    ) : (
                        <>
                            <Stat
                                label="CPU"
                                value={`${cpuPct}%`}
                                hint={`${live?.cpus ?? server.cpu_cores} vCPU allocated`}
                                icon={<Cpu className="h-5 w-5" />}
                            />
                            <Stat
                                label="Memory"
                                value={`${percent(live?.mem ?? 0, live?.maxmem ?? server.memory_mb * 1024 * 1024)}%`}
                                hint={`${bytes(live?.mem ?? 0)} of ${bytes(live?.maxmem ?? server.memory_mb * 1024 * 1024)}`}
                                icon={<MemoryStick className="h-5 w-5" />}
                            />
                            <Stat
                                label="Disk"
                                value={megabytes(server.disk_mb)}
                                hint={live?.maxdisk ? `${bytes(live.maxdisk)} provisioned` : 'Provisioned capacity'}
                                icon={<HardDrive className="h-5 w-5" />}
                            />
                            <Stat
                                label="Uptime"
                                value={state === 'running' ? uptime(live?.uptime ?? 0) : 'Offline'}
                                hint={live?.qmpstatus ? `QMP: ${live.qmpstatus}` : undefined}
                                icon={<Timer className="h-5 w-5" />}
                            />
                        </>
                    )}
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <Card title="Live utilisation" className="lg:col-span-2">
                        <div className="space-y-5">
                            <Meter label="CPU" used={cpuPct} total={100} unit="%" />
                            <Meter
                                label="Memory"
                                used={live?.mem ?? 0}
                                total={live?.maxmem ?? server.memory_mb * 1024 * 1024}
                                format={(v) => bytes(v)}
                            />
                            <Meter
                                label="Disk (provisioned)"
                                used={live?.disk ?? 0}
                                total={live?.maxdisk ?? server.disk_mb * 1024 * 1024}
                                format={(v) => bytes(v)}
                            />
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="rounded-panel border border-edge/60 bg-surface-sunken/50 px-3 py-2">
                                    <p className="flex items-center gap-2 text-xs uppercase tracking-wider text-ink-muted">
                                        <NetworkIcon className="h-3.5 w-3.5" /> Network in / out
                                    </p>
                                    <p className="mt-0.5 text-sm font-medium text-ink">
                                        {bytes(live?.netin ?? 0)} / {bytes(live?.netout ?? 0)}
                                    </p>
                                </div>
                                <div className="rounded-panel border border-edge/60 bg-surface-sunken/50 px-3 py-2">
                                    <p className="flex items-center gap-2 text-xs uppercase tracking-wider text-ink-muted">
                                        <Gauge className="h-3.5 w-3.5" /> Disk read / write
                                    </p>
                                    <p className="mt-0.5 text-sm font-medium text-ink">
                                        {bytes(live?.diskread ?? 0)} / {bytes(live?.diskwrite ?? 0)}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </Card>

                    <Card title="Machine">
                        <KeyValue
                            items={[
                                ['Node', server.node?.name ?? '—'],
                                ['Location', server.node?.location?.name ?? '—'],
                                ['VMID', server.vmid],
                                ['Plan', server.plan?.name ?? 'Custom'],
                                ['Template', server.template ?? '—'],
                                ['Bandwidth', server.bandwidth_gb ? `${server.bandwidth_gb} GB` : 'Unmetered'],
                            ]}
                        />
                        <div className="mt-4">
                            <p className="hv-label">IP allocations</p>
                            {server.allocations?.length ? (
                                <ul className="space-y-1.5">
                                    {server.allocations.map((a: any) => (
                                        <li key={a.id} className="flex items-center justify-between rounded-panel border border-edge/60 px-3 py-1.5 font-mono text-xs text-ink">
                                            <span>{a.address}{a.cidr ? `/${a.cidr}` : ''}</span>
                                            <span className="text-ink-muted">{a.type ?? 'ipv4'}</span>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <p className="text-sm text-ink-muted">No addresses assigned.</p>
                            )}
                        </div>
                    </Card>
                </div>

                <Card title="Recent panel activity">
                    {recentTasks?.length ? (
                        <ul className="divide-y divide-edge/50">
                            {recentTasks.map((task: any) => (
                                <li key={task.id} className="flex flex-wrap items-center justify-between gap-2 py-2.5 text-sm">
                                    <span className="font-medium text-ink">{task.action}</span>
                                    <span className="text-xs text-ink-muted">
                                        {task.user?.name ?? 'system'} · {new Date(task.created_at).toLocaleString()}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <EmptyState icon={<Gauge className="h-6 w-6" />} title="No activity yet" description="Power actions and changes you make will appear here." />
                    )}
                </Card>
            </div>
        </ServerLayout>
    );
}
