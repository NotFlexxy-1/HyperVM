import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Card } from '@/Components/UI';

export default function RolesIndex({ roles, availablePermissions }: any) {
    const form = useForm<any>({ name: '', description: '', colour: '#5b8cff', permissions: [] });

    const toggle = (perm: string) => form.setData('permissions',
        form.data.permissions.includes(perm) ? form.data.permissions.filter((p: string) => p !== perm) : [...form.data.permissions, perm]);

    return (
        <AppLayout title="Roles & permissions">
            <Head title="Roles" />
            <div className="grid gap-6 lg:grid-cols-2">
                <Card title="Create role">
                    <form className="space-y-3" onSubmit={(e) => { e.preventDefault(); form.post('/admin/roles', { onSuccess: () => form.reset() }); }}>
                        <div><label className="hv-label">Name</label><input className="hv-input" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} /></div>
                        <div><label className="hv-label">Description</label><input className="hv-input" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} /></div>
                        <div><label className="hv-label">Colour</label><input type="color" className="h-10 w-16 rounded-panel border border-edge bg-transparent" value={form.data.colour} onChange={(e) => form.setData('colour', e.target.value)} /></div>
                        {Object.entries(availablePermissions).map(([group, perms]: any) => (
                            <fieldset key={group}>
                                <legend className="hv-label">{group}</legend>
                                <div className="grid gap-1">
                                    {Object.entries(perms).map(([key, label]: any) => (
                                        <label key={key} className="flex items-center gap-2 text-xs text-ink-muted">
                                            <input type="checkbox" className="rounded border-edge bg-surface-sunken text-brand" checked={form.data.permissions.includes(key)} onChange={() => toggle(key)} />
                                            {label}
                                        </label>
                                    ))}
                                </div>
                            </fieldset>
                        ))}
                        <button className="hv-btn-primary w-full" disabled={form.processing}>Create role</button>
                    </form>
                </Card>

                <Card title="Existing roles">
                    <ul className="space-y-3">
                        {roles.map((r: any) => (
                            <li key={r.id} className="rounded-panel border border-edge/60 px-4 py-3">
                                <div className="flex items-center justify-between">
                                    <span className="font-semibold text-ink" style={{ color: r.colour ?? undefined }}>{r.name}</span>
                                    <span className="text-xs text-ink-muted">{r.users_count} users · {r.permissions.length} permissions</span>
                                </div>
                                <p className="mt-1 text-xs text-ink-muted">{r.description}</p>
                                {!r.is_protected && (
                                    <button className="mt-2 text-xs text-red-400" onClick={() => router.delete(`/admin/roles/${r.id}`)}>Delete role</button>
                                )}
                            </li>
                        ))}
                    </ul>
                </Card>
            </div>
        </AppLayout>
    );
}
