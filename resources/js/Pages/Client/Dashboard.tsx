import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, Stat, Badge } from '@/Components/UI';
import { megabytes } from '@/lib/format';
import type { PageProps } from '@/types';

export default function ClientDashboard({ servers, totals, filters }: any) {
    const { settings } = usePage<PageProps>().props;

    return (
        <AppLayout title="Your servers">
            <Head title={`Servers · ${settings.branding.panel_name}`} />

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Stat label="Servers" value={totals.servers} />
                <Stat label="vCPU" value={totals.cpu_cores} />
                <Stat label="Memory" value={megabytes(totals.memory_mb)} />
                <Stat label="Disk" value={megabytes(totals.disk_mb)} />
            </div>

            <Card
                title="Virtual machines"
                action={
                    <input
                        defaultValue={filters.search ?? ''}
                        placeholder="Search…"
                        className="hv-input max-w-[220px] py-1.5"
                        onKeyDown={(e) => e.key === 'Enter' && router.get('/dashboard', { search: (e.target as HTMLInputElement).value }, { preserveState: true })}
                    />
                }
            >
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {servers.data.map((server: any) => (
                        <Link key={server.id} href={`/servers/${server.uuid_short}`} className="hv-card hv-density transition hover:shadow-glow">
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <p className="truncate font-semibold text-ink">{server.name}</p>
                                    <p className="truncate text-xs text-ink-muted">
                                        {server.node?.name} · VMID {server.vmid}
                                    </p>
                                </div>
                                <Badge tone={server.status === 'ready' ? 'ok' : server.status === 'suspended' ? 'bad' : 'warn'}>
                                    {server.status}
                                </Badge>
                            </div>
                            <dl className="mt-4 grid grid-cols-3 gap-2 text-center text-xs">
                                <div><dt className="text-ink-muted">vCPU</dt><dd className="font-semibold text-ink">{server.cpu_cores}</dd></div>
                                <div><dt className="text-ink-muted">RAM</dt><dd className="font-semibold text-ink">{megabytes(server.memory_mb)}</dd></div>
                                <div><dt className="text-ink-muted">Disk</dt><dd className="font-semibold text-ink">{megabytes(server.disk_mb)}</dd></div>
                            </dl>
                            {server.allocations?.[0] && (
                                <p className="mt-3 truncate font-mono text-xs text-ink-muted">{server.allocations[0].address}</p>
                            )}
                        </Link>
                    ))}
                    {servers.data.length === 0 && <p className="text-sm text-ink-muted">No servers assigned to your account yet.</p>}
                </div>
            </Card>
        </AppLayout>
    );
}
