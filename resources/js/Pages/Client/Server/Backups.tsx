import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Archive, Camera, History, Plus, Trash2 } from 'lucide-react';
import ServerLayout from '@/Layouts/ServerLayout';
import { Badge, Card, ConfirmButton, Field, Modal, Table, Toggle } from '@/Components/UI';
import { bytes } from '@/lib/format';

const when = (value?: string | null) => (value ? new Date(value).toLocaleString() : '—');

export default function Backups({ server, permissions, backups, snapshots }: any) {
    const base = `/servers/${server.uuid_short}`;
    const can = (p: string) => permissions.includes(p);

    const [backupOpen, setBackupOpen] = useState(false);
    const [snapshotOpen, setSnapshotOpen] = useState(false);

    const backupForm = useForm({ name: '' });
    const snapshotForm = useForm({ name: '', description: '', include_ram: false });

    return (
        <ServerLayout server={server} permissions={permissions}>
            <div className="mt-6 space-y-6">
                <Card
                    title="Backups"
                    subtitle={`${backups.length} of ${server.backup_limit} slots used · full vzdump archives stored on the node`}
                    action={
                        can('backup.create') && (
                            <button className="hv-btn-primary py-1.5" onClick={() => setBackupOpen(true)} disabled={backups.length >= server.backup_limit}>
                                <Plus className="h-4 w-4" /> New backup
                            </button>
                        )
                    }
                >
                    <Table head={['Name', 'Size', 'Created', 'State', '']} empty={backups.length ? undefined : <p className="px-4 py-6 text-sm text-ink-muted">No backups yet.</p>}>
                        {backups.map((b: any) => (
                            <tr key={b.uuid}>
                                <td className="px-4 py-3">
                                    <span className="font-medium text-ink">{b.name}</span>
                                    <span className="ml-2 font-mono text-[11px] text-ink-muted">{b.compression_type}</span>
                                </td>
                                <td className="px-4 py-3">{b.size_bytes ? bytes(Number(b.size_bytes)) : '—'}</td>
                                <td className="px-4 py-3 text-ink-muted">{when(b.completed_at ?? b.created_at)}</td>
                                <td className="px-4 py-3">
                                    <Badge tone={b.is_successful ? 'ok' : 'warn'}>{b.is_successful ? 'complete' : 'pending'}</Badge>
                                </td>
                                <td className="px-4 py-3">
                                    <div className="flex justify-end gap-2">
                                        {can('backup.restore') && b.is_successful && (
                                            <ConfirmButton
                                                className="hv-btn-ghost py-1.5"
                                                title="Restore this backup?"
                                                body="The server will be stopped and its current disk contents replaced by this archive. This cannot be undone."
                                                confirmLabel="Restore"
                                                onConfirm={() => router.post(`${base}/backups/${b.uuid}/restore`, {}, { preserveScroll: true })}
                                            >
                                                <History className="h-4 w-4" /> Restore
                                            </ConfirmButton>
                                        )}
                                        {can('backup.delete') && !b.is_locked && (
                                            <ConfirmButton
                                                className="hv-btn-danger py-1.5"
                                                title="Delete this backup?"
                                                body="The archive will be permanently removed from the node's backup storage."
                                                confirmLabel="Delete"
                                                onConfirm={() => router.delete(`${base}/backups/${b.uuid}`, { preserveScroll: true })}
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </ConfirmButton>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </Table>
                </Card>

                <Card
                    title="Snapshots"
                    subtitle={`${snapshots.length} of ${server.snapshot_limit} slots used · instant point-in-time rollback`}
                    action={
                        can('snapshot.create') && (
                            <button className="hv-btn-primary py-1.5" onClick={() => setSnapshotOpen(true)} disabled={snapshots.length >= server.snapshot_limit}>
                                <Camera className="h-4 w-4" /> New snapshot
                            </button>
                        )
                    }
                >
                    <Table head={['Name', 'Description', 'RAM', 'Created', '']} empty={snapshots.length ? undefined : <p className="px-4 py-6 text-sm text-ink-muted">No snapshots yet.</p>}>
                        {snapshots.map((s: any) => (
                            <tr key={s.id}>
                                <td className="px-4 py-3 font-mono text-xs text-ink">{s.name}</td>
                                <td className="px-4 py-3 text-ink-muted">{s.description || '—'}</td>
                                <td className="px-4 py-3">{s.include_ram ? <Badge tone="brand">included</Badge> : '—'}</td>
                                <td className="px-4 py-3 text-ink-muted">{when(s.created_at)}</td>
                                <td className="px-4 py-3">
                                    <div className="flex justify-end gap-2">
                                        {can('snapshot.rollback') && (
                                            <ConfirmButton
                                                className="hv-btn-ghost py-1.5"
                                                title="Roll back to this snapshot?"
                                                body="All changes made since this snapshot was taken will be lost."
                                                confirmLabel="Roll back"
                                                onConfirm={() => router.post(`${base}/snapshots/${s.id}/rollback`, {}, { preserveScroll: true })}
                                            >
                                                <History className="h-4 w-4" /> Roll back
                                            </ConfirmButton>
                                        )}
                                        {can('snapshot.delete') && (
                                            <ConfirmButton
                                                className="hv-btn-danger py-1.5"
                                                title="Delete this snapshot?"
                                                body="The snapshot will be removed from the hypervisor."
                                                confirmLabel="Delete"
                                                onConfirm={() => router.delete(`${base}/snapshots/${s.id}`, { preserveScroll: true })}
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </ConfirmButton>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </Table>
                </Card>
            </div>

            <Modal
                open={backupOpen}
                onClose={() => setBackupOpen(false)}
                title="Create a backup"
                footer={
                    <>
                        <button className="hv-btn-ghost" onClick={() => setBackupOpen(false)}>Cancel</button>
                        <button
                            className="hv-btn-primary"
                            disabled={backupForm.processing}
                            onClick={() =>
                                backupForm.post(`${base}/backups`, {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        backupForm.reset();
                                        setBackupOpen(false);
                                    },
                                })
                            }
                        >
                            <Archive className="h-4 w-4" /> Start backup
                        </button>
                    </>
                }
            >
                <div className="space-y-4 px-5 py-4">
                    <Field label="Name" hint="Leave blank to use a timestamp." error={backupForm.errors.name}>
                        <input className="hv-input" value={backupForm.data.name} onChange={(e) => backupForm.setData('name', e.target.value)} />
                    </Field>
                    <p className="text-xs text-ink-muted">
                        Backups run in snapshot mode, so the server keeps running. Large disks can take several minutes.
                    </p>
                </div>
            </Modal>

            <Modal
                open={snapshotOpen}
                onClose={() => setSnapshotOpen(false)}
                title="Create a snapshot"
                footer={
                    <>
                        <button className="hv-btn-ghost" onClick={() => setSnapshotOpen(false)}>Cancel</button>
                        <button
                            className="hv-btn-primary"
                            disabled={snapshotForm.processing}
                            onClick={() =>
                                snapshotForm.post(`${base}/snapshots`, {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        snapshotForm.reset();
                                        setSnapshotOpen(false);
                                    },
                                })
                            }
                        >
                            <Camera className="h-4 w-4" /> Take snapshot
                        </button>
                    </>
                }
            >
                <div className="space-y-4 px-5 py-4">
                    <Field label="Name" hint="Letters, numbers, dashes and underscores." error={snapshotForm.errors.name}>
                        <input className="hv-input font-mono" value={snapshotForm.data.name} onChange={(e) => snapshotForm.setData('name', e.target.value)} />
                    </Field>
                    <Field label="Description" error={snapshotForm.errors.description}>
                        <input className="hv-input" value={snapshotForm.data.description} onChange={(e) => snapshotForm.setData('description', e.target.value)} />
                    </Field>
                    <div className="flex items-center justify-between rounded-panel border border-edge/60 px-4 py-3">
                        <div>
                            <p className="text-sm font-medium text-ink">Include memory state</p>
                            <p className="text-xs text-ink-muted">Slower, but restores the running state exactly.</p>
                        </div>
                        <Toggle checked={snapshotForm.data.include_ram} onChange={(v) => snapshotForm.setData('include_ram', v)} label="Include RAM" />
                    </div>
                </div>
            </Modal>
        </ServerLayout>
    );
}
