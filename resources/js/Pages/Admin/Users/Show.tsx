import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Card } from '@/Components/UI';

export default function UserShow({ user, roles, servers, activity }: any) {
    const profile = useForm({
        name: user.name, username: user.username, email: user.email,
        roles: user.roles.map((r: any) => r.name), is_suspended: user.is_suspended,
    });
    const password = useForm({ password: '', force_change: true, revoke_sessions: true });

    return (
        <AppLayout title={user.username}>
            <Head title={user.username} />
            <div className="grid gap-6 lg:grid-cols-2">
                <Card title="Profile">
                    <form className="space-y-3" onSubmit={(e) => { e.preventDefault(); profile.patch(`/admin/users/${user.id}`); }}>
                        {(['name', 'username', 'email'] as const).map((f) => (
                            <div key={f}><label className="hv-label">{f}</label><input className="hv-input" value={profile.data[f]} onChange={(e) => profile.setData(f, e.target.value)} /></div>
                        ))}
                        <div>
                            <label className="hv-label">Roles</label>
                            <select multiple className="hv-input h-24" value={profile.data.roles} onChange={(e) => profile.setData('roles', Array.from(e.target.selectedOptions).map((o) => o.value))}>
                                {roles.map((r: any) => <option key={r.id} value={r.name}>{r.name}</option>)}
                            </select>
                        </div>
                        <label className="flex items-center gap-2 text-sm text-ink"><input type="checkbox" className="rounded border-edge bg-surface-sunken text-brand" checked={!!profile.data.is_suspended} onChange={(e) => profile.setData('is_suspended', e.target.checked)} />Suspended</label>
                        <button className="hv-btn-primary" disabled={profile.processing}>Save</button>
                    </form>
                </Card>

                <Card title="Security">
                    <form className="space-y-3" onSubmit={(e) => { e.preventDefault(); password.post(`/admin/users/${user.id}/password`, { onSuccess: () => password.reset() }); }}>
                        <div><label className="hv-label">New password</label><input type="password" className="hv-input" value={password.data.password} onChange={(e) => password.setData('password', e.target.value)} />
                            {password.errors.password && <p className="mt-1 text-xs text-red-400">{password.errors.password}</p>}</div>
                        <label className="flex items-center gap-2 text-sm text-ink"><input type="checkbox" className="rounded border-edge bg-surface-sunken text-brand" checked={password.data.force_change} onChange={(e) => password.setData('force_change', e.target.checked)} />Force change at next login</label>
                        <label className="flex items-center gap-2 text-sm text-ink"><input type="checkbox" className="rounded border-edge bg-surface-sunken text-brand" checked={password.data.revoke_sessions} onChange={(e) => password.setData('revoke_sessions', e.target.checked)} />Revoke all sessions and API tokens</label>
                        <button className="hv-btn-danger" disabled={password.processing}>Reset password</button>
                    </form>
                </Card>

                <Card title="Servers"><ul className="divide-y divide-edge/50 text-sm">{servers.data.map((s: any) => <li key={s.id} className="py-2 text-ink">{s.name} <span className="text-ink-muted">· {s.node?.name}</span></li>)}</ul></Card>
                <Card title="Recent activity"><ul className="divide-y divide-edge/50 text-sm">{activity.map((a: any) => <li key={a.id} className="py-2 text-ink-muted">{a.action}</li>)}</ul></Card>
            </div>
        </AppLayout>
    );
}
