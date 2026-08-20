import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    Activity, KeyRound, Laptop, Lock, Save, ShieldCheck, Trash2, UserRound,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Alert, Badge, Card, ConfirmButton, Field, KeyValue, Modal, Table } from '@/Components/UI';
import type { PageProps } from '@/types';

const when = (value?: string | number | null) => {
    if (!value) return '—';
    const date = typeof value === 'number' ? new Date(value * 1000) : new Date(value);
    return Number.isNaN(date.getTime()) ? '—' : date.toLocaleString();
};

export default function Account({ apiKeys, apiPermissions, sessions, activity, security }: any) {
    const { props } = usePage<PageProps>();
    const user = props.auth.user!;
    const panelName = props.settings.branding.panel_name;

    const [keyOpen, setKeyOpen] = useState(false);
    const [setup, setSetup] = useState<{ secret: string; uri: string } | null>(null);

    const profile = useForm({ name: user.name, username: user.username, email: user.email, current_password: '' });
    const password = useForm({ current_password: '', password: '', password_confirmation: '' });
    const apiKey = useForm<{ memo: string; permissions: string[]; allowed_ips: string[]; expires_at: string }>({
        memo: '',
        permissions: [],
        allowed_ips: [],
        expires_at: '',
    });
    const confirm2fa = useForm({ code: '' });
    const disable2fa = useForm({ password: '' });

    const beginTwoFactor = async () => {
        const response = await fetch('/account/two-factor', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
            },
        });
        const payload = await response.json();
        if (payload.secret) setSetup(payload);
    };

    return (
        <AppLayout>
            <Head title={`Account · ${panelName}`} />

            <header className="hv-card hv-density animate-fade-up">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-ink">Account</h1>
                        <p className="mt-1 text-sm text-ink-muted">Manage your profile, credentials, API access and active sessions.</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Badge tone={security.two_factor_enabled ? 'ok' : 'warn'}>
                            {security.two_factor_enabled ? '2FA enabled' : '2FA disabled'}
                        </Badge>
                        {security.discord_username && <Badge tone="brand">Discord: {security.discord_username}</Badge>}
                    </div>
                </div>
            </header>

            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <Card title="Profile" subtitle="Your identity across the panel">
                    <form
                        className="space-y-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            profile.patch('/account', { preserveScroll: true, onSuccess: () => profile.setData('current_password', '') });
                        }}
                    >
                        <Field label="Full name" error={profile.errors.name}>
                            <input className="hv-input" value={profile.data.name} onChange={(e) => profile.setData('name', e.target.value)} />
                        </Field>
                        <Field label="Username" error={profile.errors.username}>
                            <input className="hv-input" value={profile.data.username} onChange={(e) => profile.setData('username', e.target.value)} />
                        </Field>
                        <Field label="Email" hint="Changing the email requires your current password." error={profile.errors.email}>
                            <input className="hv-input" type="email" value={profile.data.email} onChange={(e) => profile.setData('email', e.target.value)} />
                        </Field>
                        {profile.data.email !== user.email && (
                            <Field label="Current password" error={profile.errors.current_password}>
                                <input className="hv-input" type="password" value={profile.data.current_password} onChange={(e) => profile.setData('current_password', e.target.value)} />
                            </Field>
                        )}
                        <button className="hv-btn-primary" disabled={profile.processing}>
                            <UserRound className="h-4 w-4" /> Save profile
                        </button>
                    </form>
                </Card>

                <Card title="Password" subtitle="Changing your password signs out every other device">
                    <form
                        className="space-y-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            password.put('/account/password', { preserveScroll: true, onSuccess: () => password.reset() });
                        }}
                    >
                        <Field label="Current password" error={password.errors.current_password}>
                            <input className="hv-input" type="password" value={password.data.current_password} onChange={(e) => password.setData('current_password', e.target.value)} />
                        </Field>
                        <Field label="New password" error={password.errors.password}>
                            <input className="hv-input" type="password" value={password.data.password} onChange={(e) => password.setData('password', e.target.value)} />
                        </Field>
                        <Field label="Confirm new password">
                            <input className="hv-input" type="password" value={password.data.password_confirmation} onChange={(e) => password.setData('password_confirmation', e.target.value)} />
                        </Field>
                        <button className="hv-btn-primary" disabled={password.processing}>
                            <Lock className="h-4 w-4" /> Update password
                        </button>
                        <p className="text-xs text-ink-muted">Last changed {when(security.password_changed_at)}.</p>
                    </form>
                </Card>
            </div>

            <div className="mt-6">
                <Card title="Two-factor authentication" subtitle="Time-based one-time passwords (RFC 6238)">
                    {security.two_factor_enabled ? (
                        <div className="space-y-4">
                            <Alert tone="success">
                                Two-factor authentication is protecting this account. {security.recovery_codes_remaining} recovery codes remain.
                            </Alert>
                            <div className="flex flex-wrap gap-2">
                                <ConfirmButton
                                    className="hv-btn-ghost"
                                    title="Regenerate recovery codes?"
                                    body="Your existing recovery codes stop working immediately."
                                    confirmLabel="Regenerate"
                                    onConfirm={() => router.post('/account/two-factor/recovery-codes', {}, { preserveScroll: true })}
                                >
                                    <KeyRound className="h-4 w-4" /> Regenerate recovery codes
                                </ConfirmButton>
                                <form
                                    className="flex flex-wrap items-end gap-2"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        disable2fa.delete('/account/two-factor', { preserveScroll: true, onSuccess: () => disable2fa.reset() });
                                    }}
                                >
                                    <Field label="Password to disable" error={disable2fa.errors.password}>
                                        <input className="hv-input" type="password" value={disable2fa.data.password} onChange={(e) => disable2fa.setData('password', e.target.value)} />
                                    </Field>
                                    <button className="hv-btn-danger" disabled={disable2fa.processing}>Disable 2FA</button>
                                </form>
                            </div>
                        </div>
                    ) : setup ? (
                        <div className="space-y-4">
                            <Alert tone="info">Scan this secret in your authenticator app, then confirm with a generated code.</Alert>
                            <KeyValue
                                items={[
                                    ['Secret', <span className="font-mono text-xs">{setup.secret}</span>],
                                    ['otpauth URI', <span className="break-all font-mono text-[10px]">{setup.uri}</span>],
                                ]}
                            />
                            <form
                                className="flex flex-wrap items-end gap-2"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    confirm2fa.post('/account/two-factor/confirm', { preserveScroll: true, onSuccess: () => setSetup(null) });
                                }}
                            >
                                <Field label="Authentication code" error={confirm2fa.errors.code}>
                                    <input className="hv-input font-mono tracking-[0.3em]" inputMode="numeric" maxLength={6} value={confirm2fa.data.code} onChange={(e) => confirm2fa.setData('code', e.target.value)} />
                                </Field>
                                <button className="hv-btn-primary" disabled={confirm2fa.processing}>
                                    <ShieldCheck className="h-4 w-4" /> Confirm and enable
                                </button>
                            </form>
                        </div>
                    ) : (
                        <div className="flex flex-wrap items-center justify-between gap-4">
                            <p className="text-sm text-ink-muted">Add a second factor so a stolen password alone cannot reach your servers.</p>
                            <button className="hv-btn-primary" onClick={beginTwoFactor}>
                                <ShieldCheck className="h-4 w-4" /> Enable two-factor
                            </button>
                        </div>
                    )}
                </Card>
            </div>

            <div className="mt-6">
                <Card
                    title="API keys"
                    subtitle="Programmatic access to the HyperVM application API"
                    action={
                        <button className="hv-btn-primary py-1.5" onClick={() => setKeyOpen(true)}>
                            <KeyRound className="h-4 w-4" /> Create key
                        </button>
                    }
                >
                    <Table head={['Memo', 'Identifier', 'Scopes', 'Last used', 'Expires', '']} empty={apiKeys.length ? undefined : <p className="px-4 py-6 text-sm text-ink-muted">You have not created any API keys.</p>}>
                        {apiKeys.map((key: any) => (
                            <tr key={key.id}>
                                <td className="px-4 py-3 font-medium text-ink">{key.memo}</td>
                                <td className="px-4 py-3 font-mono text-xs text-ink-muted">hypervm_{key.identifier}</td>
                                <td className="px-4 py-3"><Badge tone="brand">{(key.permissions ?? []).length}</Badge></td>
                                <td className="px-4 py-3 text-ink-muted">{when(key.last_used_at)}</td>
                                <td className="px-4 py-3 text-ink-muted">{key.expires_at ? when(key.expires_at) : 'never'}</td>
                                <td className="px-4 py-3 text-right">
                                    <ConfirmButton
                                        className="hv-btn-danger py-1.5"
                                        title="Revoke this API key?"
                                        body="Any integration using this key stops working immediately."
                                        confirmLabel="Revoke"
                                        onConfirm={() => router.delete(`/account/api-keys/${key.id}`, { preserveScroll: true })}
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </ConfirmButton>
                                </td>
                            </tr>
                        ))}
                    </Table>
                </Card>
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <Card title="Active sessions" subtitle="Browsers currently signed in to your account">
                    <Table head={['Device', 'IP', 'Last active', '']} empty={sessions.length ? undefined : <p className="px-4 py-6 text-sm text-ink-muted">No stored sessions.</p>}>
                        {sessions.map((session: any) => (
                            <tr key={session.id}>
                                <td className="px-4 py-3">
                                    <span className="flex items-center gap-2 text-ink">
                                        <Laptop className="h-4 w-4 text-ink-muted" />
                                        <span className="max-w-[220px] truncate text-xs">{session.user_agent ?? 'Unknown device'}</span>
                                    </span>
                                    {session.is_current && <Badge tone="ok">this device</Badge>}
                                </td>
                                <td className="px-4 py-3 font-mono text-xs text-ink-muted">{session.ip_address ?? '—'}</td>
                                <td className="px-4 py-3 text-ink-muted">{when(session.last_activity)}</td>
                                <td className="px-4 py-3 text-right">
                                    {!session.is_current && (
                                        <button className="hv-btn-ghost py-1.5" onClick={() => router.delete(`/account/sessions/${session.id}`, { preserveScroll: true })}>
                                            Revoke
                                        </button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </Table>
                </Card>

                <Card title="Recent activity" subtitle="The last 25 security-relevant actions on your account">
                    <ul className="space-y-2">
                        {activity.length === 0 && <li className="text-sm text-ink-muted">No recorded activity.</li>}
                        {activity.map((entry: any) => (
                            <li key={entry.id} className="flex items-start gap-3 rounded-panel border border-edge/60 px-3 py-2">
                                <Activity className="mt-0.5 h-4 w-4 text-brand" />
                                <div className="min-w-0">
                                    <p className="font-mono text-xs text-ink">{entry.action}</p>
                                    <p className="text-xs text-ink-muted">
                                        {when(entry.created_at)} · {entry.ip_address ?? 'unknown IP'}
                                    </p>
                                </div>
                            </li>
                        ))}
                    </ul>
                    <div className="mt-4">
                        <KeyValue
                            items={[
                                ['Last sign-in', when(security.last_login_at)],
                                ['Last sign-in IP', <span className="font-mono text-xs">{security.last_login_ip ?? '—'}</span>],
                            ]}
                        />
                    </div>
                </Card>
            </div>

            <Modal
                open={keyOpen}
                onClose={() => setKeyOpen(false)}
                title="Create an API key"
                width="max-w-2xl"
                footer={
                    <>
                        <button className="hv-btn-ghost" onClick={() => setKeyOpen(false)}>Cancel</button>
                        <button
                            className="hv-btn-primary"
                            disabled={apiKey.processing}
                            onClick={() =>
                                apiKey.post('/account/api-keys', {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        apiKey.reset();
                                        setKeyOpen(false);
                                    },
                                })
                            }
                        >
                            <Save className="h-4 w-4" /> Create key
                        </button>
                    </>
                }
            >
                <div className="space-y-5">
                    <Alert tone="warn">The secret is shown once, immediately after creation. Store it somewhere safe.</Alert>
                    <Field label="Memo" error={apiKey.errors.memo}>
                        <input className="hv-input" value={apiKey.data.memo} onChange={(e) => apiKey.setData('memo', e.target.value)} />
                    </Field>
                    <Field label="Allowed IP addresses" hint="Comma separated. Leave blank to allow any address.">
                        <input
                            className="hv-input font-mono text-xs"
                            onChange={(e) =>
                                apiKey.setData(
                                    'allowed_ips',
                                    e.target.value.split(',').map((v) => v.trim()).filter(Boolean),
                                )
                            }
                        />
                    </Field>
                    <Field label="Expires at" hint="Optional. Leave blank for a non-expiring key.">
                        <input className="hv-input" type="datetime-local" value={apiKey.data.expires_at} onChange={(e) => apiKey.setData('expires_at', e.target.value)} />
                    </Field>
                    <div className="space-y-4">
                        {Object.entries(apiPermissions ?? {}).map(([group, items]: any) => (
                            <div key={group}>
                                <p className="hv-label">{group}</p>
                                <div className="grid gap-2 sm:grid-cols-2">
                                    {Object.entries(items).map(([key, label]: any) => (
                                        <label key={key} className="flex cursor-pointer items-start gap-2 rounded-panel border border-edge/60 px-3 py-2 text-xs text-ink-muted transition hover:border-brand/40">
                                            <input
                                                type="checkbox"
                                                className="mt-0.5 accent-[var(--hv-brand)]"
                                                checked={apiKey.data.permissions.includes(key)}
                                                onChange={() =>
                                                    apiKey.setData(
                                                        'permissions',
                                                        apiKey.data.permissions.includes(key)
                                                            ? apiKey.data.permissions.filter((p) => p !== key)
                                                            : [...apiKey.data.permissions, key],
                                                    )
                                                }
                                            />
                                            <span>
                                                <span className="block font-medium text-ink">{label}</span>
                                                <span className="font-mono text-[10px]">{key}</span>
                                            </span>
                                        </label>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </Modal>
        </AppLayout>
    );
}
