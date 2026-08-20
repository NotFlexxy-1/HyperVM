import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState, type ReactNode } from 'react';
import {
    Activity, Disc3, Gauge, HardDriveDownload, Network, Play, Power, RefreshCw, Settings2, SquareTerminal, StopCircle,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Badge, StatusDot } from '@/Components/UI';
import type { PageProps } from '@/types';

export interface ServerShell {
    server: any;
    permissions: string[];
}

const TABS = [
    { label: 'Overview', path: '', icon: Gauge, permission: null },
    { label: 'Console', path: '/console', icon: SquareTerminal, permission: 'control.console' },
    { label: 'Resources', path: '/resources', icon: HardDriveDownload, permission: null },
    { label: 'Network', path: '/network', icon: Network, permission: 'network.read' },
    { label: 'Media', path: '/media', icon: Disc3, permission: 'media.manage' },
    { label: 'Backups', path: '/backups', icon: HardDriveDownload, permission: 'backup.read' },
    { label: 'Activity', path: '/activity', icon: Activity, permission: 'activity.read' },
    { label: 'Settings', path: '/settings', icon: Settings2, permission: null },
];

export default function ServerLayout({
    server,
    permissions,
    state,
    children,
}: ServerShell & { state?: string; children: ReactNode }) {
    const { url, props } = usePage<PageProps>();
    const [busy, setBusy] = useState<string | null>(null);
    const base = `/servers/${server.uuid_short}`;
    const panelName = props.settings.branding.panel_name;

    const tabs = useMemo(() => TABS.filter((t) => !t.permission || permissions.includes(t.permission)), [permissions]);

    const power = (action: string) => {
        setBusy(action);
        router.post(`${base}/power`, { action }, { preserveScroll: true, onFinish: () => setBusy(null) });
    };

    const controllable = server.status === 'ready' && !server.is_locked;
    const running = state === 'running';

    return (
        <AppLayout>
            <Head title={`${server.name} · ${panelName}`} />

            <header className="hv-card hv-density animate-fade-up">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2.5">
                            {state && <StatusDot state={state} />}
                            <h1 className="truncate text-2xl font-bold tracking-tight text-ink">{server.name}</h1>
                            <Badge tone={server.status === 'ready' ? 'ok' : server.status === 'suspended' ? 'bad' : 'warn'}>{server.status}</Badge>
                            {server.is_locked && <Badge tone="warn">locked</Badge>}
                        </div>
                        <p className="mt-1 truncate text-sm text-ink-muted">
                            {server.node?.name}
                            {server.node?.location?.short_code ? ` · ${server.node.location.short_code}` : ''} · VMID {server.vmid}
                            {server.allocations?.[0] ? ` · ${server.allocations[0].address}` : ''}
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {permissions.includes('control.start') && (
                            <button className="hv-btn-primary" disabled={!controllable || running || busy !== null} onClick={() => power('start')}>
                                <Play className="h-4 w-4" /> Start
                            </button>
                        )}
                        {permissions.includes('control.restart') && (
                            <button className="hv-btn-ghost" disabled={!controllable || !running || busy !== null} onClick={() => power('reboot')}>
                                <RefreshCw className={`h-4 w-4 ${busy === 'reboot' ? 'animate-spin' : ''}`} /> Restart
                            </button>
                        )}
                        {permissions.includes('control.stop') && (
                            <>
                                <button className="hv-btn-ghost" disabled={!controllable || !running || busy !== null} onClick={() => power('shutdown')}>
                                    <Power className="h-4 w-4" /> Shutdown
                                </button>
                                <button className="hv-btn-danger" disabled={!controllable || busy !== null} onClick={() => power('stop')}>
                                    <StopCircle className="h-4 w-4" /> Stop
                                </button>
                            </>
                        )}
                    </div>
                </div>

                <nav className="hv-scroll -mb-1 mt-5 flex gap-1 overflow-x-auto border-t border-edge/60 pt-4">
                    {tabs.map(({ label, path, icon: Icon }) => {
                        const href = `${base}${path}`;
                        const active = path === '' ? url === base : url.startsWith(href);
                        return (
                            <Link
                                key={label}
                                href={href}
                                className={`flex shrink-0 items-center gap-2 rounded-panel px-3 py-2 text-sm font-medium transition ${
                                    active ? 'bg-brand/15 text-brand' : 'text-ink-muted hover:bg-surface-sunken hover:text-ink'
                                }`}
                            >
                                <Icon className="h-4 w-4" />
                                {label}
                            </Link>
                        );
                    })}
                </nav>
            </header>

            {children}
        </AppLayout>
    );
}
