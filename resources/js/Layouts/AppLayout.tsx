import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, type ReactNode } from 'react';
import {
    Boxes, Gauge, HardDrive, LayoutDashboard, LogOut, MapPin, Moon, Package,
    ScrollText, Server, Settings, Shield, Sun, Users,
} from 'lucide-react';
import { useTheme } from '@/lib/theme';
import type { PageProps } from '@/types';

const clientNav = [
    { label: 'Dashboard', href: '/dashboard', icon: LayoutDashboard },
    { label: 'Account', href: '/account', icon: Shield },
];

const adminNav = [
    { label: 'Overview', href: '/admin', icon: Gauge },
    { label: 'Servers', href: '/admin/servers', icon: Server },
    { label: 'Nodes', href: '/admin/nodes', icon: HardDrive },
    { label: 'Locations', href: '/admin/locations', icon: MapPin },
    { label: 'Plans', href: '/admin/plans', icon: Package },
    { label: 'Users', href: '/admin/users', icon: Users },
    { label: 'Roles', href: '/admin/roles', icon: Boxes },
    { label: 'Audit log', href: '/admin/audit-logs', icon: ScrollText },
    { label: 'Settings', href: '/admin/settings', icon: Settings },
];

export default function AppLayout({ children, title }: { children: ReactNode; title?: string }) {
    const { props, url } = usePage<PageProps>();
    const { branding, layout } = props.settings;
    const user = props.auth.user;
    const { resolved, setMode } = useTheme();

    useEffect(() => {
        if (props.flash.error) console.warn(props.flash.error);
    }, [props.flash.error]);

    const container = { boxed: 'max-w-5xl', wide: 'max-w-7xl', fluid: 'max-w-none' }[layout.container];
    const nav = url.startsWith('/admin') ? adminNav : clientNav;
    const isRail = layout.navigation === 'rail';
    const isTopbar = layout.navigation === 'topbar';

    const NavLinks = () => (
        <>
            {nav.map(({ label, href, icon: Icon }) => {
                const active = url === href || (href !== '/admin' && url.startsWith(href));
                return (
                    <Link key={href} href={href}
                        className={`flex items-center gap-3 rounded-panel px-3 py-2 text-sm font-medium transition ${
                            active ? 'bg-brand/15 text-brand' : 'text-ink-muted hover:bg-surface-sunken hover:text-ink'
                        }`}>
                        <Icon className="h-4 w-4 shrink-0" />
                        {!isRail && <span>{label}</span>}
                    </Link>
                );
            })}
        </>
    );

    return (
        <div className={`density-${layout.density} min-h-screen`}>
            {isTopbar ? (
                <header className="sticky top-0 z-40 border-b border-edge/60 bg-surface-raised/80 backdrop-blur-xl">
                    <div className={`mx-auto flex ${container} items-center gap-6 px-6 py-3`}>
                        <Brand branding={branding} />
                        <nav className="flex flex-1 flex-wrap items-center gap-1"><NavLinks /></nav>
                        <UserMenu user={user} resolved={resolved} setMode={setMode} allow={props.settings.theme.allow_user_mode_switch} />
                    </div>
                </header>
            ) : null}

            <div className="flex">
                {!isTopbar && (
                    <aside className={`sticky top-0 hidden h-screen shrink-0 border-r border-edge/60 bg-surface-raised/60 p-4 backdrop-blur-xl md:flex md:flex-col ${isRail ? 'w-[76px] items-center' : 'w-64'}`}>
                        <Brand branding={branding} compact={isRail} />
                        <nav className="mt-6 flex flex-1 flex-col gap-1"><NavLinks /></nav>
                        {user?.is_admin && !url.startsWith('/admin') && !isRail && (
                            <Link href="/admin" className="hv-btn-ghost mt-2 w-full">Admin area</Link>
                        )}
                        {user?.is_admin && url.startsWith('/admin') && !isRail && (
                            <Link href="/dashboard" className="hv-btn-ghost mt-2 w-full">Client area</Link>
                        )}
                        <div className="mt-4 border-t border-edge/60 pt-4">
                            <UserMenu user={user} resolved={resolved} setMode={setMode} compact={isRail} allow={props.settings.theme.allow_user_mode_switch} />
                        </div>
                    </aside>
                )}

                <main className="min-w-0 flex-1">
                    <div className={`mx-auto ${container} space-y-6 px-5 py-8 md:px-8`}>
                        {title && (
                            <div className="flex flex-wrap items-end justify-between gap-3">
                                <h1 className="text-2xl font-bold tracking-tight text-ink">{title}</h1>
                                {branding.tagline && <p className="text-sm text-ink-muted">{branding.tagline}</p>}
                            </div>
                        )}
                        {props.flash.success && (
                            <div className="hv-card border-emerald-500/40 px-4 py-3 text-sm text-emerald-400">{props.flash.success}</div>
                        )}
                        {props.flash.error && (
                            <div className="hv-card border-red-500/40 px-4 py-3 text-sm text-red-400">{props.flash.error}</div>
                        )}
                        {children}
                    </div>
                </main>
            </div>
        </div>
    );
}

const Brand = ({ branding, compact = false }: { branding: PageProps['settings']['branding']; compact?: boolean }) => (
    <Link href="/dashboard" className="flex items-center gap-2.5">
        {branding.logo_url ? (
            <img src={branding.logo_url} alt={branding.panel_name} className="h-8 w-8 rounded-panel object-contain" />
        ) : (
            <span className="grid h-8 w-8 place-items-center rounded-panel bg-gradient-to-br from-brand to-accent text-sm font-black text-brand-contrast">
                {branding.panel_name.slice(0, 1)}
            </span>
        )}
        {!compact && <span className="text-base font-bold tracking-tight text-ink">{branding.panel_name}</span>}
    </Link>
);

const UserMenu = ({ user, resolved, setMode, compact = false, allow }: any) => (
    <div className={`flex items-center gap-2 ${compact ? 'flex-col' : ''}`}>
        {allow && (
            <button className="hv-btn-ghost px-2" title="Toggle theme" onClick={() => setMode(resolved === 'dark' ? 'light' : 'dark')}>
                {resolved === 'dark' ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />}
            </button>
        )}
        {!compact && user && (
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-semibold text-ink">{user.name}</p>
                <p className="truncate text-xs text-ink-muted">{user.email}</p>
            </div>
        )}
        <button className="hv-btn-ghost px-2" title="Sign out" onClick={() => router.post('/logout')}>
            <LogOut className="h-4 w-4" />
        </button>
    </div>
);
