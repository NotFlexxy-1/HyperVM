import { Head, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Card } from '@/Components/UI';

export default function CreateServer({ nodes, plans, templates }: any) {
    const form = useForm<any>({
        name: '', description: '', owner_id: '', node_id: nodes[0]?.id ?? '', plan_id: '',
        cpu_cores: 2, memory_mb: 2048, disk_mb: 20480, bandwidth_gb: '', network_speed_mbps: '',
        template: Object.keys(templates)[0] ?? '', allocation_ids: [], root_password: '', ssh_keys: '',
        start_after_install: true,
    });
    const [allocations, setAllocations] = useState<any[]>([]);
    const [users, setUsers] = useState<any[]>([]);

    useEffect(() => {
        if (!form.data.node_id) return;
        fetch(`/admin/nodes/${form.data.node_id}/available-allocations`, { headers: { Accept: 'application/json' } })
            .then((r) => r.json()).then(setAllocations).catch(() => setAllocations([]));
    }, [form.data.node_id]);

    const searchUsers = (query: string) => {
        fetch(`/admin/user-search?query=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' } })
            .then((r) => r.json()).then(setUsers).catch(() => setUsers([]));
    };

    return (
        <AppLayout title="Provision server">
            <Head title="Provision server" />
            <form onSubmit={(e) => { e.preventDefault(); form.post('/admin/servers'); }} className="grid gap-6 lg:grid-cols-2">
                <Card title="Identity & owner">
                    <div className="space-y-4">
                        <div><label className="hv-label">Name</label><input className="hv-input" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} /></div>
                        <div><label className="hv-label">Description</label><textarea rows={2} className="hv-input" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} /></div>
                        <div>
                            <label className="hv-label">Owner</label>
                            <input className="hv-input" placeholder="Search users…" onChange={(e) => searchUsers(e.target.value)} />
                            <select className="hv-input mt-2" value={form.data.owner_id} onChange={(e) => form.setData('owner_id', e.target.value)}>
                                <option value="">Select owner</option>
                                {users.map((u) => <option key={u.id} value={u.id}>{u.username} — {u.email}</option>)}
                            </select>
                            {form.errors.owner_id && <p className="mt-1 text-xs text-red-400">{form.errors.owner_id}</p>}
                        </div>
                    </div>
                </Card>

                <Card title="Placement & resources">
                    <div className="space-y-4">
                        <div>
                            <label className="hv-label">Node</label>
                            <select className="hv-input" value={form.data.node_id} onChange={(e) => form.setData('node_id', e.target.value)}>
                                {nodes.map((n: any) => <option key={n.id} value={n.id}>{n.name}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="hv-label">Plan (optional)</label>
                            <select className="hv-input" value={form.data.plan_id} onChange={(e) => form.setData('plan_id', e.target.value)}>
                                <option value="">Custom resources</option>
                                {plans.map((p: any) => <option key={p.id} value={p.id}>{p.name}</option>)}
                            </select>
                        </div>
                        {!form.data.plan_id && (
                            <div className="grid grid-cols-3 gap-3">
                                <div><label className="hv-label">vCPU</label><input type="number" className="hv-input" value={form.data.cpu_cores} onChange={(e) => form.setData('cpu_cores', Number(e.target.value))} /></div>
                                <div><label className="hv-label">RAM MiB</label><input type="number" className="hv-input" value={form.data.memory_mb} onChange={(e) => form.setData('memory_mb', Number(e.target.value))} /></div>
                                <div><label className="hv-label">Disk MiB</label><input type="number" className="hv-input" value={form.data.disk_mb} onChange={(e) => form.setData('disk_mb', Number(e.target.value))} /></div>
                            </div>
                        )}
                        <div>
                            <label className="hv-label">Template</label>
                            <select className="hv-input" value={form.data.template} onChange={(e) => form.setData('template', e.target.value)}>
                                {Object.entries(templates).map(([key, label]: any) => <option key={key} value={key}>{label}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="hv-label">IP allocations</label>
                            <select multiple className="hv-input h-28" value={form.data.allocation_ids}
                                onChange={(e) => form.setData('allocation_ids', Array.from(e.target.selectedOptions).map((o) => Number(o.value)))}>
                                {allocations.map((a) => <option key={a.id} value={a.id}>{a.address}/{a.cidr}</option>)}
                            </select>
                        </div>
                    </div>
                </Card>

                <Card title="Cloud-init" className="lg:col-span-2">
                    <div className="grid gap-4 md:grid-cols-2">
                        <div><label className="hv-label">Root password</label><input type="password" className="hv-input" value={form.data.root_password} onChange={(e) => form.setData('root_password', e.target.value)} /></div>
                        <div><label className="hv-label">SSH public keys</label><textarea rows={3} className="hv-input" value={form.data.ssh_keys} onChange={(e) => form.setData('ssh_keys', e.target.value)} /></div>
                        <label className="flex items-center gap-2 text-sm text-ink"><input type="checkbox" className="rounded border-edge bg-surface-sunken text-brand" checked={form.data.start_after_install} onChange={(e) => form.setData('start_after_install', e.target.checked)} />Boot after provisioning</label>
                        <div className="md:col-span-2"><button className="hv-btn-primary" disabled={form.processing}>Create on Proxmox</button></div>
                    </div>
                </Card>
            </form>
        </AppLayout>
    );
}
