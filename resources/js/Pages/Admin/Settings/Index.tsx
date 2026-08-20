import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Card } from '@/Components/UI';

const TABS = ['Branding', 'Colours', 'Layout', 'Access'] as const;

export default function SettingsIndex({ settings, advanced, widgetCatalogue }: any) {
    const [tab, setTab] = useState<(typeof TABS)[number]>('Branding');

    return (
        <AppLayout title="Panel settings">
            <Head title="Settings" />
            <nav className="flex flex-wrap gap-2">
                {TABS.map((t) => (
                    <button key={t} onClick={() => setTab(t)} className={t === tab ? 'hv-btn-primary py-1.5' : 'hv-btn-ghost py-1.5'}>{t}</button>
                ))}
            </nav>
            {tab === 'Branding' && <Branding branding={settings.branding} />}
            {tab === 'Colours' && <Colours theme={settings.theme} />}
            {tab === 'Layout' && <LayoutEditor layout={settings.layout} catalogue={widgetCatalogue} />}
            {tab === 'Access' && <Access settings={settings} advanced={advanced} />}
        </AppLayout>
    );
}

function Branding({ branding }: any) {
    const form = useForm({
        panel_name: branding.panel_name ?? '',
        tagline: branding.tagline ?? '',
        social_description: branding.social_description ?? '',
    });
    const logo = useForm<{ logo: File | null }>({ logo: null });
    const favicon = useForm<{ favicon: File | null }>({ favicon: null });

    return (
        <div className="grid gap-6 lg:grid-cols-2">
            <Card title="Identity">
                <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); form.post('/admin/settings/branding'); }}>
                    <div>
                        <label className="hv-label">Panel name</label>
                        <input className="hv-input" value={form.data.panel_name} onChange={(e) => form.setData('panel_name', e.target.value)} />
                        {form.errors.panel_name && <p className="mt-1 text-xs text-red-400">{form.errors.panel_name}</p>}
                    </div>
                    <div>
                        <label className="hv-label">Tagline</label>
                        <input className="hv-input" value={form.data.tagline} onChange={(e) => form.setData('tagline', e.target.value)} />
                    </div>
                    <div>
                        <label className="hv-label">Social / meta description</label>
                        <textarea rows={3} className="hv-input" value={form.data.social_description} onChange={(e) => form.setData('social_description', e.target.value)} />
                        <p className="mt-1 text-xs text-ink-muted">Used for the meta description, Open Graph and Twitter cards.</p>
                    </div>
                    <button className="hv-btn-primary" disabled={form.processing}>Save branding</button>
                </form>
            </Card>

            <Card title="Logo & favicon">
                <div className="space-y-6">
                    <div>
                        <p className="hv-label">Logo</p>
                        <div className="flex flex-wrap items-center gap-3">
                            {branding.logo_url && <img src={branding.logo_url} alt="" className="h-12 w-12 rounded-panel object-contain" />}
                            <input type="file" accept=".png,.jpg,.jpeg,.svg,.webp" className="text-sm text-ink-muted" onChange={(e) => logo.setData('logo', e.target.files?.[0] ?? null)} />
                            <button className="hv-btn-primary" disabled={!logo.data.logo || logo.processing} onClick={() => logo.post('/admin/settings/logo', { forceFormData: true })}>Upload</button>
                            {branding.logo_url && <button className="hv-btn-ghost" onClick={() => router.delete('/admin/settings/asset', { data: { asset: 'logo' } })}>Remove</button>}
                        </div>
                    </div>
                    <div>
                        <p className="hv-label">Favicon</p>
                        <div className="flex flex-wrap items-center gap-3">
                            {branding.favicon_url && <img src={branding.favicon_url} alt="" className="h-8 w-8 rounded object-contain" />}
                            <input type="file" accept=".png,.ico,.svg,.webp" className="text-sm text-ink-muted" onChange={(e) => favicon.setData('favicon', e.target.files?.[0] ?? null)} />
                            <button className="hv-btn-primary" disabled={!favicon.data.favicon || favicon.processing} onClick={() => favicon.post('/admin/settings/favicon', { forceFormData: true })}>Upload</button>
                            {branding.favicon_url && <button className="hv-btn-ghost" onClick={() => router.delete('/admin/settings/asset', { data: { asset: 'favicon' } })}>Remove</button>}
                        </div>
                    </div>
                </div>
            </Card>
        </div>
    );
}

function Colours({ theme }: any) {
    const form = useForm({ ...theme });
    const swatches = [['brand', 'Primary'], ['brand_soft', 'Primary hover'], ['brand_contrast', 'On primary'], ['accent', 'Accent']] as const;

    const rgb = (hex: string) => {
        const clean = hex.replace('#', '');
        const n = parseInt(clean.length === 3 ? clean.split('').map((c) => c + c).join('') : clean, 16);
        return `${(n >> 16) & 255} ${(n >> 8) & 255} ${n & 255}`;
    };

    const apply = (key: string, value: string) => {
        form.setData(key as never, value as never);
        if (/^#(?:[0-9a-f]{3}){1,2}$/i.test(value)) {
            document.documentElement.style.setProperty(`--hv-${key.replace('_', '-')}`, rgb(value));
        }
    };

    return (
        <Card title="Colour & typography">
            <form className="grid gap-6 lg:grid-cols-2" onSubmit={(e) => { e.preventDefault(); form.post('/admin/settings/theme'); }}>
                <div className="space-y-4">
                    {swatches.map(([key, label]) => (
                        <div key={key} className="flex items-center gap-3">
                            <input type="color" value={form.data[key]} onChange={(e) => apply(key, e.target.value)} className="h-10 w-14 cursor-pointer rounded-panel border border-edge bg-transparent" />
                            <div className="flex-1">
                                <label className="hv-label mb-0">{label}</label>
                                <input className="hv-input mt-1 font-mono" value={form.data[key]} onChange={(e) => apply(key, e.target.value)} />
                            </div>
                        </div>
                    ))}
                </div>
                <div className="space-y-4">
                    <div>
                        <label className="hv-label">Corner radius</label>
                        <input className="hv-input" value={form.data.radius} onChange={(e) => form.setData('radius', e.target.value)} placeholder="14px" />
                    </div>
                    <div>
                        <label className="hv-label">Font family</label>
                        <select className="hv-input" value={form.data.font} onChange={(e) => form.setData('font', e.target.value)}>
                            {['Inter', 'Manrope', 'Sora', 'Outfit', 'Space Grotesk', 'JetBrains Mono', 'Figtree'].map((f) => <option key={f}>{f}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="hv-label">Default mode</label>
                        <select className="hv-input" value={form.data.default_mode} onChange={(e) => form.setData('default_mode', e.target.value)}>
                            <option value="dark">Dark</option><option value="light">Light</option><option value="system">Follow system</option>
                        </select>
                    </div>
                    <label className="flex items-center gap-2 text-sm text-ink">
                        <input type="checkbox" className="rounded border-edge bg-surface-sunken text-brand" checked={!!form.data.allow_user_mode_switch} onChange={(e) => form.setData('allow_user_mode_switch', e.target.checked)} />
                        Let users switch between light and dark
                    </label>
                    <div className="hv-card hv-density">
                        <p className="text-xs uppercase tracking-wider text-ink-muted">Live preview</p>
                        <div className="mt-3 flex flex-wrap gap-2">
                            <span className="hv-btn-primary">Primary action</span>
                            <span className="hv-btn-ghost">Secondary</span>
                            <span className="hv-chip">Chip</span>
                        </div>
                    </div>
                    <button className="hv-btn-primary" disabled={form.processing}>Save theme</button>
                </div>
            </form>
        </Card>
    );
}

function LayoutEditor({ layout, catalogue }: any) {
    const initial = Object.keys(catalogue).map((key) => layout.dashboard_widgets?.find((w: any) => w.key === key) ?? { key, span: 6, enabled: false });
    const form = useForm({
        navigation: layout.navigation,
        density: layout.density,
        container: layout.container,
        dashboard_widgets: initial,
    });

    const move = (index: number, delta: number) => {
        const next = [...form.data.dashboard_widgets];
        const target = index + delta;
        if (target < 0 || target >= next.length) return;
        [next[index], next[target]] = [next[target], next[index]];
        form.setData('dashboard_widgets', next);
    };

    const patch = (index: number, changes: Record<string, unknown>) => {
        const next = [...form.data.dashboard_widgets];
        next[index] = { ...next[index], ...changes };
        form.setData('dashboard_widgets', next);
    };

    return (
        <form className="grid gap-6 lg:grid-cols-3" onSubmit={(e) => { e.preventDefault(); form.post('/admin/settings/layout'); }}>
            <Card title="Shell" className="lg:col-span-1">
                <div className="space-y-4">
                    {([['navigation', ['sidebar', 'topbar', 'rail']], ['density', ['compact', 'comfortable', 'spacious']], ['container', ['boxed', 'wide', 'fluid']]] as const).map(([key, options]) => (
                        <div key={key}>
                            <label className="hv-label">{key}</label>
                            <div className="grid grid-cols-3 gap-2">
                                {options.map((option) => (
                                    <button key={option} type="button" onClick={() => form.setData(key as never, option as never)}
                                        className={form.data[key] === option ? 'hv-btn-primary py-1.5 text-xs' : 'hv-btn-ghost py-1.5 text-xs'}>{option}</button>
                                ))}
                            </div>
                        </div>
                    ))}
                    <button className="hv-btn-primary w-full" disabled={form.processing}>Save layout</button>
                </div>
            </Card>

            <Card title="Dashboard widget grid" className="lg:col-span-2">
                <ul className="space-y-2">
                    {form.data.dashboard_widgets.map((widget: any, index: number) => (
                        <li key={widget.key} className="flex flex-wrap items-center gap-3 rounded-panel border border-edge/60 px-3 py-2">
                            <input type="checkbox" checked={widget.enabled} onChange={(e) => patch(index, { enabled: e.target.checked })} className="rounded border-edge bg-surface-sunken text-brand" />
                            <span className="flex-1 text-sm text-ink">{catalogue[widget.key]}</span>
                            <select value={widget.span} onChange={(e) => patch(index, { span: Number(e.target.value) })} className="hv-input w-28 py-1 text-xs">
                                {[3, 4, 6, 8, 9, 12].map((s) => <option key={s} value={s}>{s} / 12</option>)}
                            </select>
                            <div className="flex gap-1">
                                <button type="button" className="hv-btn-ghost px-2 py-1 text-xs" onClick={() => move(index, -1)}>Up</button>
                                <button type="button" className="hv-btn-ghost px-2 py-1 text-xs" onClick={() => move(index, 1)}>Down</button>
                            </div>
                        </li>
                    ))}
                </ul>
                <p className="mt-3 text-xs text-ink-muted">Order and width apply to the admin dashboard grid immediately after saving.</p>
            </Card>
        </form>
    );
}

function Access({ settings, advanced }: any) {
    const form = useForm({
        registration_enabled: settings.registration.enabled,
        require_email_verification: advanced.require_email_verification,
        allowed_email_domains: advanced.allowed_email_domains ?? [],
        default_role: advanced.default_role,
        discord_enabled: settings.registration.discord_enabled,
        discord_required_guild_id: advanced.discord_required_guild_id ?? '',
        discord_allow_account_creation: advanced.discord_allow_account_creation,
    });

    return (
        <div className="grid gap-6 lg:grid-cols-2">
            <Card title="Registration & Discord">
                <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); form.post('/admin/settings/access'); }}>
                    <label className="flex items-center justify-between gap-3 text-sm text-ink">
                        Allow public registration
                        <input type="checkbox" className="rounded border-edge bg-surface-sunken text-brand" checked={!!form.data.registration_enabled} onChange={(e) => form.setData('registration_enabled', e.target.checked)} />
                    </label>
                    <label className="flex items-center justify-between gap-3 text-sm text-ink">
                        Require email verification
                        <input type="checkbox" className="rounded border-edge bg-surface-sunken text-brand" checked={!!form.data.require_email_verification} onChange={(e) => form.setData('require_email_verification', e.target.checked)} />
                    </label>
                    <div>
                        <label className="hv-label">Allowed email domains (comma separated, blank = any)</label>
                        <input className="hv-input" value={(form.data.allowed_email_domains as string[]).join(', ')} onChange={(e) => form.setData('allowed_email_domains', e.target.value.split(',').map((d) => d.trim()).filter(Boolean))} />
                    </div>
                    <div>
                        <label className="hv-label">Default role for new accounts</label>
                        <input className="hv-input" value={form.data.default_role} onChange={(e) => form.setData('default_role', e.target.value)} />
                        {form.errors.default_role && <p className="mt-1 text-xs text-red-400">{form.errors.default_role}</p>}
                    </div>
                    <hr className="border-edge/60" />
                    <label className="flex items-center justify-between gap-3 text-sm text-ink">
                        Enable Discord sign-in
                        <input type="checkbox" className="rounded border-edge bg-surface-sunken text-brand" checked={!!form.data.discord_enabled} onChange={(e) => form.setData('discord_enabled', e.target.checked)} />
                    </label>
                    <label className="flex items-center justify-between gap-3 text-sm text-ink">
                        Discord may create new accounts
                        <input type="checkbox" className="rounded border-edge bg-surface-sunken text-brand" checked={!!form.data.discord_allow_account_creation} onChange={(e) => form.setData('discord_allow_account_creation', e.target.checked)} />
                    </label>
                    <div>
                        <label className="hv-label">Required Discord guild ID (optional)</label>
                        <input className="hv-input font-mono" value={form.data.discord_required_guild_id} onChange={(e) => form.setData('discord_required_guild_id', e.target.value)} />
                    </div>
                    <button className="hv-btn-primary" disabled={form.processing}>Save access settings</button>
                </form>
            </Card>

            <Card title="OAuth status">
                <p className="text-sm text-ink-muted">Discord credentials come from DISCORD_CLIENT_ID and DISCORD_CLIENT_SECRET in .env.</p>
                <p className="mt-3 text-sm">
                    Status: <span className={advanced.discord_configured ? 'text-emerald-400' : 'text-amber-400'}>{advanced.discord_configured ? 'credentials present' : 'not configured'}</span>
                </p>
                <p className="mt-3 text-xs text-ink-muted">Redirect URL: /auth/discord/callback</p>
            </Card>
        </div>
    );
}
