import { useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import { Cable, Plus, RefreshCw, Shield, Trash2 } from 'lucide-react';
import ServerLayout from '@/Layouts/ServerLayout';
import { Alert, Badge, Card, ConfirmButton, Field, KeyValue, Modal, Spinner, Table, Toggle } from '@/Components/UI';

export default function Network({ server, permissions, config, firewall, rules, guestInterfaces, error }: any) {
    const canEdit = permissions.includes('network.update');
    const base = `/servers/${server.uuid_short}`;
    const [ruleOpen, setRuleOpen] = useState(false);

    const fw = useForm({
        enable: Boolean(Number(firewall?.enable ?? 0)),
        policy_in: (firewall?.policy_in as string) ?? 'DROP',
        policy_out: (firewall?.policy_out as string) ?? 'ACCEPT',
    });

    const rate = useForm({ rate: server.network_rate_mbps ?? 0 });

    const rule = useForm({
        type: 'in',
        action: 'ACCEPT',
        proto: 'tcp',
        dport: '',
        source: '',
        comment: '',
        enable: true,
    });

    const nics = Object.entries(config ?? {}).filter(([key]) => /^net\d+$/.test(key)) as Array<[string, string]>;

    return (
        <ServerLayout server={server} permissions={permissions}>
            <div className="mt-6 space-y-6">
                {error && <Alert tone="error">{error}</Alert>}

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card
                        title="IP allocations"
                        subtitle="Assigned from your node's address pools"
                        action={
                            canEdit && (
                                <button
                                    className="hv-btn-ghost py-1.5"
                                    onClick={() => router.post(`${base}/network/sync`, {}, { preserveScroll: true })}
                                >
                                    <RefreshCw className="h-4 w-4" /> Write to hypervisor
                                </button>
                            )
                        }
                    >
                        <Table head={['Address', 'Gateway', 'Type', 'MAC']} empty="No addresses assigned to this server.">
                            {server.allocations?.map((a: any) => (
                                <tr key={a.id}>
                                    <td className="font-mono text-xs">{a.address}{a.cidr ? `/${a.cidr}` : ''}</td>
                                    <td className="font-mono text-xs">{a.gateway ?? '—'}</td>
                                    <td><Badge tone="brand">{a.type ?? 'ipv4'}</Badge></td>
                                    <td className="font-mono text-xs">{a.mac_address ?? '—'}</td>
                                </tr>
                            ))}
                        </Table>
                    </Card>

                    <Card title="Virtual interfaces">
                        {nics.length ? (
                            <KeyValue items={nics.map(([key, value]) => [key, <span key={key} className="font-mono text-xs">{value}</span>])} />
                        ) : (
                            <p className="text-sm text-ink-muted">No network devices are attached to this VM.</p>
                        )}
                        {guestInterfaces?.length > 0 && (
                            <div className="mt-4">
                                <p className="hv-label">Reported by the guest agent</p>
                                <ul className="space-y-1.5">
                                    {guestInterfaces.map((iface: any) => (
                                        <li key={iface.name} className="rounded-panel border border-edge/60 px-3 py-2 text-xs">
                                            <span className="font-semibold text-ink">{iface.name}</span>
                                            <span className="ml-2 font-mono text-ink-muted">
                                                {(iface['ip-addresses'] ?? []).map((ip: any) => ip['ip-address']).join(', ') || 'no address'}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                        <form
                            className="mt-5 flex flex-wrap items-end gap-3 border-t border-edge/60 pt-4"
                            onSubmit={(e) => {
                                e.preventDefault();
                                rate.patch(`${base}/network/rate`, { preserveScroll: true });
                            }}
                        >
                            <Field label="Rate limit (Mbps, 0 = unlimited)" error={rate.errors.rate} className="flex-1">
                                <div className="relative">
                                    <Cable className="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-ink-muted" />
                                    <input
                                        type="number"
                                        min={0}
                                        className="hv-input pl-9"
                                        disabled={!canEdit}
                                        value={rate.data.rate}
                                        onChange={(e) => rate.setData('rate', Number(e.target.value))}
                                    />
                                </div>
                            </Field>
                            <button className="hv-btn-primary" disabled={!canEdit || rate.processing}>
                                {rate.processing ? <Spinner /> : null} Apply
                            </button>
                        </form>
                    </Card>
                </div>

                <Card
                    title="Firewall"
                    subtitle="Proxmox VE firewall applied at the hypervisor, before traffic reaches the guest"
                    action={
                        canEdit && (
                            <button className="hv-btn-primary py-1.5" onClick={() => setRuleOpen(true)}>
                                <Plus className="h-4 w-4" /> Add rule
                            </button>
                        )
                    }
                >
                    <form
                        className="flex flex-wrap items-end gap-4 border-b border-edge/60 pb-5"
                        onSubmit={(e) => {
                            e.preventDefault();
                            fw.patch(`${base}/network/firewall`, { preserveScroll: true });
                        }}
                    >
                        <Toggle checked={fw.data.enable} onChange={(v) => canEdit && fw.setData('enable', v)} label="Firewall enabled" />
                        <Field label="Inbound policy" className="w-44">
                            <select className="hv-input" disabled={!canEdit} value={fw.data.policy_in} onChange={(e) => fw.setData('policy_in', e.target.value)}>
                                <option value="ACCEPT">ACCEPT</option>
                                <option value="DROP">DROP</option>
                                <option value="REJECT">REJECT</option>
                            </select>
                        </Field>
                        <Field label="Outbound policy" className="w-44">
                            <select className="hv-input" disabled={!canEdit} value={fw.data.policy_out} onChange={(e) => fw.setData('policy_out', e.target.value)}>
                                <option value="ACCEPT">ACCEPT</option>
                                <option value="DROP">DROP</option>
                                <option value="REJECT">REJECT</option>
                            </select>
                        </Field>
                        <button className="hv-btn-primary" disabled={!canEdit || fw.processing}>
                            <Shield className="h-4 w-4" /> Save policy
                        </button>
                    </form>

                    <div className="pt-4">
                        <Table head={['#', 'Direction', 'Action', 'Protocol', 'Port', 'Source', 'Comment', '']} empty="No firewall rules defined.">
                            {(rules ?? []).map((r: any) => (
                                <tr key={r.pos}>
                                    <td className="tabular-nums text-ink-muted">{r.pos}</td>
                                    <td><Badge>{r.type}</Badge></td>
                                    <td><Badge tone={r.action === 'ACCEPT' ? 'ok' : 'bad'}>{r.action}</Badge></td>
                                    <td className="uppercase">{r.proto ?? 'any'}</td>
                                    <td className="font-mono text-xs">{r.dport ?? 'any'}</td>
                                    <td className="font-mono text-xs">{r.source ?? 'any'}</td>
                                    <td className="truncate text-xs text-ink-muted">{r.comment ?? ''}</td>
                                    <td className="text-right">
                                        {canEdit && (
                                            <ConfirmButton
                                                className="hv-btn-danger px-2 py-1.5"
                                                title="Delete firewall rule"
                                                body={`Rule #${r.pos} will be removed immediately.`}
                                                onConfirm={() => router.delete(`${base}/network/firewall/rules/${r.pos}`, { preserveScroll: true })}
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </ConfirmButton>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </Table>
                    </div>
                </Card>
            </div>

            <Modal open={ruleOpen} onClose={() => setRuleOpen(false)} title="New firewall rule">
                <form
                    className="space-y-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        rule.post(`${base}/network/firewall/rules`, {
                            preserveScroll: true,
                            onSuccess: () => {
                                rule.reset();
                                setRuleOpen(false);
                            },
                        });
                    }}
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Direction" error={rule.errors.type}>
                            <select className="hv-input" value={rule.data.type} onChange={(e) => rule.setData('type', e.target.value)}>
                                <option value="in">Inbound</option>
                                <option value="out">Outbound</option>
                            </select>
                        </Field>
                        <Field label="Action" error={rule.errors.action}>
                            <select className="hv-input" value={rule.data.action} onChange={(e) => rule.setData('action', e.target.value)}>
                                <option value="ACCEPT">ACCEPT</option>
                                <option value="DROP">DROP</option>
                                <option value="REJECT">REJECT</option>
                            </select>
                        </Field>
                        <Field label="Protocol" error={rule.errors.proto}>
                            <select className="hv-input" value={rule.data.proto} onChange={(e) => rule.setData('proto', e.target.value)}>
                                <option value="">any</option>
                                <option value="tcp">tcp</option>
                                <option value="udp">udp</option>
                                <option value="icmp">icmp</option>
                            </select>
                        </Field>
                        <Field label="Destination port" error={rule.errors.dport} hint="e.g. 22, 80:443">
                            <input className="hv-input" value={rule.data.dport} onChange={(e) => rule.setData('dport', e.target.value)} />
                        </Field>
                    </div>
                    <Field label="Source" error={rule.errors.source} hint="CIDR or IP, empty for any">
                        <input className="hv-input font-mono text-xs" value={rule.data.source} onChange={(e) => rule.setData('source', e.target.value)} />
                    </Field>
                    <Field label="Comment" error={rule.errors.comment}>
                        <input className="hv-input" value={rule.data.comment} onChange={(e) => rule.setData('comment', e.target.value)} />
                    </Field>
                    <div className="flex justify-end gap-2">
                        <button type="button" className="hv-btn-ghost" onClick={() => setRuleOpen(false)}>
                            Cancel
                        </button>
                        <button className="hv-btn-primary" disabled={rule.processing}>
                            {rule.processing ? <Spinner /> : <Plus className="h-4 w-4" />} Create rule
                        </button>
                    </div>
                </form>
            </Modal>
        </ServerLayout>
    );
}
