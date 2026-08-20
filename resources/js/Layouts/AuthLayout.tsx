import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import type { PageProps } from '@/types';

export default function AuthLayout({ title, subtitle, children }: { title: string; subtitle?: string; children: ReactNode }) {
    const { branding, theme } = usePage<PageProps>().props.settings;

    return (
        <div className="grid min-h-screen lg:grid-cols-2">
            <div className="relative hidden overflow-hidden border-r border-edge/60 lg:block">
                <div className="absolute inset-0 bg-gradient-to-br from-brand/25 via-transparent to-accent/25" />
                <div className="relative flex h-full flex-col justify-between p-12">
                    <div className="flex items-center gap-3">
                        {branding.logo_url
                            ? <img src={branding.logo_url} alt="" className="h-10 w-10 rounded-panel object-contain" />
                            : <span className="grid h-10 w-10 place-items-center rounded-panel bg-brand text-lg font-black text-brand-contrast">{branding.panel_name.slice(0, 1)}</span>}
                        <span className="text-xl font-bold text-ink">{branding.panel_name}</span>
                    </div>
                    <div>
                        <h2 className="max-w-md text-4xl font-black leading-tight tracking-tight text-ink">
                            {branding.tagline}
                        </h2>
                        <p className="mt-4 max-w-md text-sm text-ink-muted">{branding.social_description}</p>
                    </div>
                    <p className="text-xs text-ink-muted">Powered by Proxmox VE · {theme.font}</p>
                </div>
            </div>

            <div className="flex items-center justify-center p-6">
                <div className="w-full max-w-sm">
                    <h1 className="text-2xl font-bold tracking-tight text-ink">{title}</h1>
                    {subtitle && <p className="mt-1 text-sm text-ink-muted">{subtitle}</p>}
                    <div className="mt-6">{children}</div>
                </div>
            </div>
        </div>
    );
}
