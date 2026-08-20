import { router, useForm } from '@inertiajs/react';
import { CircleSlash2, Disc3, ListOrdered } from 'lucide-react';
import ServerLayout from '@/Layouts/ServerLayout';
import { Alert, Badge, Card, ConfirmButton, Field, Table } from '@/Components/UI';
import { bytes } from '@/lib/format';

export default function Media({ server, permissions, images, config, error }: any) {
    const base = `/servers/${server.uuid_short}`;
    const canManage = permissions.includes('media.manage');

    // Proxmox exposes the mounted ISO on the ide2 (or similar) cdrom device.
    const mounted = Object.entries(config ?? {}).find(
        ([key, value]) => /^(ide|sata|scsi)\d+$/.test(key) && String(value).includes('media=cdrom') && !String(value).startsWith('none'),
    ) as [string, string] | undefined;

    const boot = useForm({ order: String(config?.boot ?? '').replace(/^order=/, '') || 'scsi0;ide2;net0' });

    return (
        <ServerLayout server={server} permissions={permissions}>
            <div className="mt-6 space-y-6">
                {error && <Alert tone="error">{error}</Alert>}

                <Card
                    title="Virtual CD-ROM"
                    subtitle="Attach installation media from your node's ISO storage"
                    action={
                        mounted && canManage ? (
                            <ConfirmButton
                                className="hv-btn-ghost py-1.5"
                                title="Unmount ISO"
                                body="The virtual drive will be emptied. Running installers may fail."
                                onConfirm={() => router.post(`${base}/media/unmount`, {}, { preserveScroll: true })}
                            >
                                <CircleSlash2 className="h-4 w-4" /> Unmount
                            </ConfirmButton>
                        ) : null
                    }
                >
                    {mounted ? (
                        <div className="flex flex-wrap items-center gap-3 rounded-panel border border-edge/60 px-4 py-3">
                            <Disc3 className="h-5 w-5 text-brand" />
                            <div className="min-w-0">
                                <p className="truncate font-mono text-xs text-ink">{mounted[1].split(',')[0]}</p>
                                <p className="text-xs text-ink-muted">Attached to {mounted[0]}</p>
                            </div>
                            <Badge tone="ok">mounted</Badge>
                        </div>
                    ) : (
                        <p className="text-sm text-ink-muted">No installation media is currently attached.</p>
                    )}
                </Card>

                <Card title="Available images" subtitle="ISO content reported by the hypervisor storage">
                    <Table head={['Image', 'Size', '']} empty="No ISO images are available on this node.">
                        {(images ?? []).map((image: any) => (
                            <tr key={image.volid}>
                                <td className="font-mono text-xs">{String(image.volid).split('/').pop()}</td>
                                <td>{image.size ? bytes(Number(image.size)) : '—'}</td>
                                <td className="text-right">
                                    {canManage && (
                                        <button
                                            className="hv-btn-ghost py-1.5"
                                            onClick={() =>
                                                router.post(`${base}/media/mount`, { volid: image.volid }, { preserveScroll: true })
                                            }
                                        >
                                            Mount
                                        </button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </Table>
                </Card>

                {canManage && (
                    <Card title="Boot order" subtitle="Semicolon separated device list, first match wins">
                        <form
                            className="space-y-4"
                            onSubmit={(e) => {
                                e.preventDefault();
                                boot.patch(`${base}/media/boot-order`, { preserveScroll: true });
                            }}
                        >
                            <Field label="Order" hint="For example: ide2;scsi0;net0" error={boot.errors.order}>
                                <input
                                    className="hv-input font-mono"
                                    value={boot.data.order}
                                    onChange={(e) => boot.setData('order', e.target.value)}
                                />
                            </Field>
                            <button className="hv-btn-primary" disabled={boot.processing}>
                                <ListOrdered className="h-4 w-4" /> Save boot order
                            </button>
                        </form>
                    </Card>
                )}
            </div>
        </ServerLayout>
    );
}
