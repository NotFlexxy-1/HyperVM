import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { AlertTriangle, Save, Trash2, UserPlus, Users } from 'lucide-react';
import ServerLayout from '@/Layouts/ServerLayout';
import { Alert, Badge, Card, ConfirmButton, Field, KeyValue, Modal, Table } from '@/Components/UI';
import { megabytes } from '@/lib/format';

export default function Settings({ server, permissions, subusers, subuserPermissions, templates }: any) {
    const base = `/servers/${server.uuid_short}`;
    const can = (p: string) => permissions.includes(p);

    const [inviteOpen, setInviteOpen] = useState(false);
    const [editing, setEditing] = useState<any>(null);

    const details = useForm({ name: server.name, description: server.description ?? '' });
    const reinstall = useForm({ template: Object.keys(templates ?? {})[0] ?? '', root_password: '', ssh_keys: '', confirm: true });
    const invite = useForm<{ email: string; permissions: string[] }>({ email: '', permissions: [] });

    const parsePermissions = (user: any): string[] => {
        const raw = user?.pivot?.permissions;
        if (!raw) return [];
        return Array.isArray(raw) ? raw : JSON.parse(raw);
    };

    const togglePermission = (form: any, key: string) => {
        const current: string[] = form.data.permissions;
        form.setData('permissions', current.includes(key) ? current.filter((p) => p !== key) : [...current, key]);
    };

    const PermissionGrid = ({ form }: { form: any }) => (
        <div className="space-y-4">
            {Object.entries(subuserPermissions ?? {}).map(([group, items]: any) => (
                <div key={group}>
                    <p className="hv-label">{group}</p>
                    <div className="grid gap-2 sm:grid-cols-2">
                        {Object.entries(items).map(([key, label]: any) => (
                            <label key={key} className="flex cursor-pointer items-start gap-2 rounded-panel border border-edge/60 px-3 py-2 text-xs text-ink-muted transition hover:border-brand/40">
                                <input
                                    type="checkbox"
                                    className="mt-0.5 accent-[var(--hv-brand)]"
                                    checked={form.data.permissions.includes(key)}
                                    onChange={() => togglePermission(form, key)}
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
    );

    return (
        <ServerLayout server={server} permissions={permissions}>
            <div className="mt-6 space-y-6">
                <div className="grid gap-6 lg:grid-cols-2">
                    <Card title="Server details" subtitle="Shown across the panel and in your dashboard">
                        <form
                            className="space-y-4"
                            onSubmit={(e) => {
                                e.preventDefault();
                                details.patch(`${base}/settings`, { preserveScroll: true });
                            }}
                        >
                            <Field label="Name" error={details.errors.name}>
                                <input className="hv-input" value={details.data.name} onChange={(e) => details.setData('name', e.target.value)} disabled={!can('settings.rename')} />
                            </Field>
                            <Field label="Description" error={details.errors.description}>
                                <textarea
                                    className="hv-input min-h-[92px]"
                                    value={details.data.description}
                                    onChange={(e) => details.setData('description', e.target.value)}
                                    disabled={!can('settings.rename')}
                                />
                            </Field>
                            {can('settings.rename') && (
                                <button className="hv-btn-primary" disabled={details.processing}>
                                    <Save className="h-4 w-4" /> Save details
                                </button>
                            )}
                        </form>
                    </Card>

                    <Card title="Identity" subtitle="Immutable identifiers for support requests">
                        <KeyValue
                            items={[
                                ['Server ID', <span className="font-mono text-xs">{server.uuid_short}</span>],
                                ['UUID', <span className="font-mono text-xs">{server.uuid}</span>],
                                ['VMID', <span className="font-mono text-xs">{server.vmid}</span>],
                                ['Node', server.node?.name ?? '—'],
                                ['Location', server.node?.location?.name ?? '—'],
                                ['Plan', server.plan?.name ?? 'Custom'],
                                ['Allocated memory', megabytes(server.memory_mb)],
                                ['Allocated disk', megabytes(server.disk_mb)],
                            ]}
                        />
                    </Card>
                </div>

                {(can('subuser.read') || can('subuser.manage')) && (
                    <Card
                        title="Sub-users"
                        subtitle="Give other panel accounts scoped access to this server"
                        action={
                            can('subuser.manage') && (
                                <button className="hv-btn-primary py-1.5" onClick={() => setInviteOpen(true)}>
                                    <UserPlus className="h-4 w-4" /> Invite
                                </button>
                            )
                        }
                    >
                        <Table head={['User', 'Email', 'Permissions', '']} empty={subusers.length ? undefined : <p className="px-4 py-6 text-sm text-ink-muted">No one else has access to this server.</p>}>
                            {subusers.map((user: any) => (
                                <tr key={user.id}>
                                    <td className="px-4 py-3">
                                        <span className="font-medium text-ink">{user.name}</span>
                                        <span className="ml-2 font-mono text-[11px] text-ink-muted">@{user.username}</span>
                                    </td>
                                    <td className="px-4 py-3 text-ink-muted">{user.email}</td>
                                    <td className="px-4 py-3">
                                        <Badge tone="brand">{parsePermissions(user).length} granted</Badge>
                                    </td>
                                    <td className="px-4 py-3">
                                        {can('subuser.manage') && (
                                            <div className="flex justify-end gap-2">
                                                <button className="hv-btn-ghost py-1.5" onClick={() => setEditing(user)}>
                                                    <Users className="h-4 w-4" /> Edit
                                                </button>
                                                <ConfirmButton
                                                    className="hv-btn-danger py-1.5"
                                                    title="Remove this sub-user?"
                                                    body="They will immediately lose all access to this server."
                                                    confirmLabel="Remove"
                                                    onConfirm={() => router.delete(`${base}/subusers/${user.id}`, { preserveScroll: true })}
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </ConfirmButton>
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </Table>
                    </Card>
                )}

                {can('settings.reinstall') && (
                    <Card title="Reinstall" subtitle="Rebuild this server from a clean template">
                        <Alert tone="error">
                            <span className="flex items-start gap-2">
                                <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                                Reinstalling destroys every file on the disk. Take a backup first if you need the current data.
                            </span>
                        </Alert>
                        <div className="mt-4 grid gap-4 lg:grid-cols-2">
                            <Field label="Template" error={reinstall.errors.template}>
                                <select className="hv-input" value={reinstall.data.template} onChange={(e) => reinstall.setData('template', e.target.value)}>
                                    {Object.entries(templates ?? {}).map(([key, label]: any) => (
                                        <option key={key} value={key}>
                                            {typeof label === 'string' ? label : label?.name ?? key}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                            <Field label="Root password" hint="Leave blank to keep the existing cloud-init password." error={reinstall.errors.root_password}>
                                <input type="password" className="hv-input" value={reinstall.data.root_password} onChange={(e) => reinstall.setData('root_password', e.target.value)} />
                            </Field>
                            <Field label="SSH keys" className="lg:col-span-2" error={reinstall.errors.ssh_keys}>
                                <textarea
                                    className="hv-input min-h-[92px] font-mono text-xs"
                                    placeholder="ssh-ed25519 AAAA…"
                                    value={reinstall.data.ssh_keys}
                                    onChange={(e) => reinstall.setData('ssh_keys', e.target.value)}
                                />
                            </Field>
                        </div>
                        <div className="mt-4">
                            <ConfirmButton
                                title="Reinstall this server?"
                                body="All data on the disk will be erased and the selected template written in its place."
                                confirmLabel="Reinstall"
                                disabled={reinstall.processing}
                                onConfirm={() => reinstall.post(`${base}/reinstall`, { preserveScroll: true })}
                            >
                                <AlertTriangle className="h-4 w-4" /> Reinstall server
                            </ConfirmButton>
                        </div>
                    </Card>
                )}
            </div>

            <Modal
                open={inviteOpen}
                onClose={() => setInviteOpen(false)}
                title="Invite a sub-user"
                width="max-w-2xl"
                footer={
                    <>
                        <button className="hv-btn-ghost" onClick={() => setInviteOpen(false)}>Cancel</button>
                        <button
                            className="hv-btn-primary"
                            disabled={invite.processing}
                            onClick={() =>
                                invite.post(`${base}/subusers`, {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        invite.reset();
                                        setInviteOpen(false);
                                    },
                                })
                            }
                        >
                            <UserPlus className="h-4 w-4" /> Grant access
                        </button>
                    </>
                }
            >
                <div className="space-y-5 px-5 py-4">
                    <Field label="Account email" hint="The user must already have a HyperVM account." error={invite.errors.email}>
                        <input className="hv-input" type="email" value={invite.data.email} onChange={(e) => invite.setData('email', e.target.value)} />
                    </Field>
                    <PermissionGrid form={invite} />
                </div>
            </Modal>

            <SubuserEditor
                key={editing?.id ?? 'none'}
                user={editing}
                base={base}
                groups={subuserPermissions}
                initial={editing ? parsePermissions(editing) : []}
                onClose={() => setEditing(null)}
            />
        </ServerLayout>
    );
}

function SubuserEditor({ user, base, groups, initial, onClose }: any) {
    const form = useForm<{ permissions: string[] }>({ permissions: initial });

    if (!user) return null;

    const toggle = (key: string) =>
        form.setData(
            'permissions',
            form.data.permissions.includes(key) ? form.data.permissions.filter((p) => p !== key) : [...form.data.permissions, key],
        );

    return (
        <Modal
            open
            onClose={onClose}
            title={`Permissions · ${user.name}`}
            width="max-w-2xl"
            footer={
                <>
                    <button className="hv-btn-ghost" onClick={onClose}>Cancel</button>
                    <button
                        className="hv-btn-primary"
                        disabled={form.processing}
                        onClick={() => form.patch(`${base}/subusers/${user.id}`, { preserveScroll: true, onSuccess: onClose })}
                    >
                        <Save className="h-4 w-4" /> Save permissions
                    </button>
                </>
            }
        >
            <div className="space-y-4 px-5 py-4">
                {Object.entries(groups ?? {}).map(([group, items]: any) => (
                    <div key={group}>
                        <p className="hv-label">{group}</p>
                        <div className="grid gap-2 sm:grid-cols-2">
                            {Object.entries(items).map(([key, label]: any) => (
                                <label key={key} className="flex cursor-pointer items-start gap-2 rounded-panel border border-edge/60 px-3 py-2 text-xs text-ink-muted transition hover:border-brand/40">
                                    <input type="checkbox" className="mt-0.5 accent-[var(--hv-brand)]" checked={form.data.permissions.includes(key)} onChange={() => toggle(key)} />
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
        </Modal>
    );
}
