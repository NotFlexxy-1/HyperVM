import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, Badge } from '@/Components/UI';

export default function UsersIndex({ users, roles, filters }: any) {
    const form = useForm({ name: '', username: '', email: '', password: '', roles: ['user'] });

    return (
        <AppLayout title="Users">
            <Head title="Users" />
            <div className="grid gap-6 lg:grid-cols-3">
                <Card title="Create user">
                    <form className="space-y-3" onSubmit={(e) => { e.preventDefault(); form.post('/admin/users', { onSuccess: () => form.reset() }); }}>
                        {(['name', 'username', 'email'] as const).map((f) => (
                            <div key={f}>
                                <label className="hv-label">{f}</label>
                                <input className="hv-input" value={form.data[f]} onChange={(e) => form.setData(f, e.target.value)} />
                                {form.errors[f] && <p className="mt-1 text-xs text-red-400">{form.errors[f]}</p>}
                            </div>
                        ))}
                        <div><label className="hv-label">Password (blank = generated)</label><input type="password" className="hv-input" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} /></div>
                        <div>
                            <label className="hv-label">Roles</label>
                            <select multiple className="hv-input h-24" value={form.data.roles} onChange={(e) => form.setData('roles', Array.from(e.target.selectedOptions).map((o) => o.value))}>
                                {roles.map((r: any) => <option key={r.id} value={r.name}>{r.name}</option>)}
                            </select>
                        </div>
                        <button className="hv-btn-primary w-full" disabled={form.processing}>Create user</button>
                    </form>
                </Card>

                <Card title="Accounts" className="lg:col-span-2">
                    <input defaultValue={filters.search ?? ''} placeholder="Search…" className="hv-input mb-4 max-w-sm"
                        onKeyDown={(e) => e.key === 'Enter' && router.get('/admin/users', { search: (e.target as HTMLInputElement).value }, { preserveState: true })} />
                    <table className="w-full text-left text-sm">
                        <thead className="text-xs uppercase tracking-wider text-ink-muted"><tr><th className="pb-2">User</th><th>Roles</th><th>Servers</th><th>State</th></tr></thead>
                        <tbody className="divide-y divide-edge/50">
                            {users.data.map((u: any) => (
                                <tr key={u.id}>
                                    <td className="py-2"><Link className="font-medium text-ink hover:text-brand" href={`/admin/users/${u.id}`}>{u.username}</Link><div className="text-xs text-ink-muted">{u.email}</div></td>
                                    <td className="text-ink-muted">{u.roles.map((r: any) => r.name).join(', ')}</td>
                                    <td className="text-ink-muted">{u.servers_count}</td>
                                    <td><Badge tone={u.is_suspended ? 'bad' : 'ok'}>{u.is_suspended ? 'suspended' : 'active'}</Badge></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </Card>
            </div>
        </AppLayout>
    );
}
