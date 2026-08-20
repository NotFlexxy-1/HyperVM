import { useForm } from '@inertiajs/react';
import { Cpu, HardDrive, KeyRound, MemoryStick, Save } from 'lucide-react';
import ServerLayout from '@/Layouts/ServerLayout';
import { Alert, Card, Field, KeyValue, Meter, Spinner } from '@/Components/UI';
import { megabytes } from '@/lib/format';

export default function Resources({ server, permissions, config, error }: any) {
    const canHardware = permissions.includes('settings.hardware');
    const canCloudInit = permissions.includes('settings.cloudinit');
    const plan = server.plan;

    const hardware = useForm({
        cpu_cores: server.cpu_cores,
        memory_mb: server.memory_mb,
        disk_mb: server.disk_mb,
    });

    const cloudInit = useForm({
        ci_user: (config?.ciuser as string) ?? '',
        root_password: '',
        ssh_keys: config?.sshkeys ? decodeURIComponent(config.sshkeys) : '',
        nameserver: (config?.nameserver as string) ?? '',
        searchdomain: (config?.searchdomain as string) ?? '',
    });

    const base = `/servers/${server.uuid_short}`;

    return (
        <ServerLayout server={server} permissions={permissions}>
            <div className="mt-6 space-y-6">
                {error && <Alert tone="error">{error}</Alert>}

                <div className="grid gap-6 lg:grid-cols-3">
                    <Card title="Allocated hardware" className="lg:col-span-2">
                        <form
                            className="space-y-5"
                            onSubmit={(e) => {
                                e.preventDefault();
                                hardware.patch(`${base}/resources`, { preserveScroll: true });
                            }}
                        >
                            <div className="grid gap-4 sm:grid-cols-3">
                                <Field label="vCPU cores" error={hardware.errors.cpu_cores} hint={plan ? `Plan limit: ${plan.cpu_cores}` : undefined}>
                                    <div className="relative">
                                        <Cpu className="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-ink-muted" />
                                        <input
                                            type="number"
                                            min={1}
                                            max={plan?.cpu_cores ?? 128}
                                            className="hv-input pl-9"
                                            disabled={!canHardware}
                                            value={hardware.data.cpu_cores}
                                            onChange={(e) => hardware.setData('cpu_cores', Number(e.target.value))}
                                        />
                                    </div>
                                </Field>
                                <Field label="Memory (MB)" error={hardware.errors.memory_mb} hint={plan ? `Plan limit: ${megabytes(plan.memory_mb)}` : undefined}>
                                    <div className="relative">
                                        <MemoryStick className="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-ink-muted" />
                                        <input
                                            type="number"
                                            min={256}
                                            step={256}
                                            max={plan?.memory_mb ?? undefined}
                                            className="hv-input pl-9"
                                            disabled={!canHardware}
                                            value={hardware.data.memory_mb}
                                            onChange={(e) => hardware.setData('memory_mb', Number(e.target.value))}
                                        />
                                    </div>
                                </Field>
                                <Field label="Disk (MB)" error={hardware.errors.disk_mb} hint={plan ? `Plan limit: ${megabytes(plan.disk_mb)}` : 'Disks can only grow'}>
                                    <div className="relative">
                                        <HardDrive className="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-ink-muted" />
                                        <input
                                            type="number"
                                            min={1024}
                                            step={1024}
                                            max={plan?.disk_mb ?? undefined}
                                            className="hv-input pl-9"
                                            disabled={!canHardware}
                                            value={hardware.data.disk_mb}
                                            onChange={(e) => hardware.setData('disk_mb', Number(e.target.value))}
                                        />
                                    </div>
                                </Field>
                            </div>

                            {plan && (
                                <div className="space-y-4">
                                    <Meter label="vCPU of plan" used={hardware.data.cpu_cores} total={plan.cpu_cores} />
                                    <Meter label="Memory of plan" used={hardware.data.memory_mb} total={plan.memory_mb} format={(v) => megabytes(v)} />
                                    <Meter label="Disk of plan" used={hardware.data.disk_mb} total={plan.disk_mb} format={(v) => megabytes(v)} />
                                </div>
                            )}

                            <div className="flex items-center gap-3">
                                <button className="hv-btn-primary" disabled={!canHardware || hardware.processing}>
                                    {hardware.processing ? <Spinner /> : <Save className="h-4 w-4" />} Apply hardware
                                </button>
                                <p className="text-xs text-ink-muted">CPU and memory changes require a restart; disk growth applies live.</p>
                            </div>
                            {!canHardware && <Alert tone="warn">You do not have permission to change this server&apos;s hardware.</Alert>}
                        </form>
                    </Card>

                    <Card title="Hypervisor configuration">
                        <KeyValue
                            items={[
                                ['Cores', config?.cores ?? server.cpu_cores],
                                ['Sockets', config?.sockets ?? 1],
                                ['Memory', config?.memory ? `${config.memory} MB` : megabytes(server.memory_mb)],
                                ['Boot order', config?.boot ?? '—'],
                                ['OS type', config?.ostype ?? server.os_type ?? '—'],
                                ['SCSI HW', config?.scsihw ?? '—'],
                                ['Primary disk', config?.scsi0 ?? config?.virtio0 ?? '—'],
                                ['Agent', config?.agent ? 'enabled' : 'disabled'],
                            ]}
                        />
                    </Card>
                </div>

                <Card title="Cloud-init" subtitle="Written to the VM's cloud-init drive and applied on the next boot">
                    <form
                        className="space-y-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            cloudInit.patch(`${base}/cloud-init`, { preserveScroll: true, onSuccess: () => cloudInit.setData('root_password', '') });
                        }}
                    >
                        <div className="grid gap-4 md:grid-cols-2">
                            <Field label="Default user" error={cloudInit.errors.ci_user} hint="Lowercase letters, digits, hyphen and underscore">
                                <input className="hv-input" disabled={!canCloudInit} value={cloudInit.data.ci_user} onChange={(e) => cloudInit.setData('ci_user', e.target.value)} />
                            </Field>
                            <Field label="Password" error={cloudInit.errors.root_password} hint="Leave empty to keep the current password">
                                <div className="relative">
                                    <KeyRound className="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-ink-muted" />
                                    <input
                                        type="password"
                                        autoComplete="new-password"
                                        className="hv-input pl-9"
                                        disabled={!canCloudInit}
                                        value={cloudInit.data.root_password}
                                        onChange={(e) => cloudInit.setData('root_password', e.target.value)}
                                    />
                                </div>
                            </Field>
                            <Field label="Nameserver" error={cloudInit.errors.nameserver}>
                                <input className="hv-input" disabled={!canCloudInit} value={cloudInit.data.nameserver} onChange={(e) => cloudInit.setData('nameserver', e.target.value)} />
                            </Field>
                            <Field label="Search domain" error={cloudInit.errors.searchdomain}>
                                <input className="hv-input" disabled={!canCloudInit} value={cloudInit.data.searchdomain} onChange={(e) => cloudInit.setData('searchdomain', e.target.value)} />
                            </Field>
                        </div>
                        <Field label="SSH public keys" error={cloudInit.errors.ssh_keys} hint="One key per line">
                            <textarea
                                rows={5}
                                className="hv-input font-mono text-xs"
                                disabled={!canCloudInit}
                                value={cloudInit.data.ssh_keys}
                                onChange={(e) => cloudInit.setData('ssh_keys', e.target.value)}
                            />
                        </Field>
                        <button className="hv-btn-primary" disabled={!canCloudInit || cloudInit.processing}>
                            {cloudInit.processing ? <Spinner /> : <Save className="h-4 w-4" />} Save cloud-init
                        </button>
                        {!canCloudInit && <Alert tone="warn">You do not have permission to change cloud-init settings.</Alert>}
                    </form>
                </Card>
            </div>
        </ServerLayout>
    );
}
