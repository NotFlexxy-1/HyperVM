import { useForm } from '@inertiajs/react';
import { Card } from '@/Components/UI';

const fields: [string, string, string][] = [
    ['name', 'Display name', 'text'], ['fqdn', 'FQDN / IP', 'text'], ['port', 'API port', 'number'],
    ['proxmox_node_name', 'Proxmox node name', 'text'], ['cluster', 'Cluster (optional)', 'text'],
    ['token_id', 'API token ID (user@realm!token)', 'text'], ['token_secret', 'API token secret', 'password'],
    ['storage_pool', 'VM storage pool', 'text'], ['backup_storage_pool', 'Backup storage pool', 'text'],
    ['iso_storage_pool', 'ISO storage pool', 'text'], ['network_bridge', 'Network bridge', 'text'],
    ['memory_mb', 'Memory (MiB)', 'number'], ['memory_overallocate', 'Memory over-allocation %', 'number'],
    ['disk_mb', 'Disk (MiB)', 'number'], ['disk_overallocate', 'Disk over-allocation %', 'number'],
    ['cpu_cores', 'CPU cores', 'number'], ['cpu_overallocate', 'CPU over-allocation %', 'number'],
    ['vm_limit', 'VM limit (blank = unlimited)', 'number'],
];

export default function NodeForm({ node, locations, method, action }: any) {
    const form = useForm({
        name: node?.name ?? '', fqdn: node?.fqdn ?? '', port: node?.port ?? 8006,
        location_id: node?.location_id ?? '', cluster: node?.cluster ?? '',
        proxmox_node_name: node?.proxmox_node_name ?? '', token_id: node?.token_id ?? '', token_secret: '',
        verify_tls: node?.verify_tls ?? true, storage_pool: node?.storage_pool ?? 'local-lvm',
        backup_storage_pool: node?.backup_storage_pool ?? '', iso_storage_pool: node?.iso_storage_pool ?? '',
        network_bridge: node?.network_bridge ?? 'vmbr0', memory_mb: node?.memory_mb ?? 0,
        memory_overallocate: node?.memory_overallocate ?? 0, disk_mb: node?.disk_mb ?? 0,
        disk_overallocate: node?.disk_overallocate ?? 0, cpu_cores: node?.cpu_cores ?? 0,
        cpu_overallocate: node?.cpu_overallocate ?? 0, vm_limit: node?.vm_limit ?? '',
        is_maintenance: node?.is_maintenance ?? false, is_deployable: node?.is_deployable ?? true,
        notes: node?.notes ?? '',
    });

    return (
        <Card title="Connection & capacity">
            <form className="grid gap-4 md:grid-cols-2" onSubmit={(e) => { e.preventDefault(); method === 'patch' ? form.patch(action) : form.post(action); }}>
                <div>
                    <label className="hv-label">Location</label>
                    <select className="hv-input" value={form.data.location_id as any} onChange={(e) => form.setData('location_id', e.target.value as any)}>
                        <option value="">Unassigned</option>
                        {locations.map((l: any) => <option key={l.id} value={l.id}>{l.name}</option>)}
                    </select>
                </div>
                {fields.map(([key, label, type]) => (
                    <div key={key}>
                        <label className="hv-label">{label}</label>
                        <input type={type} className="hv-input" value={(form.data as any)[key] ?? ''} onChange={(e) => form.setData(key as never, (type === 'number' ? Number(e.target.value) : e.target.value) as never)} />
                        {(form.errors as any)[key] && <p className="mt-1 text-xs text-red-400">{(form.errors as any)[key]}</p>}
                    </div>
                ))}
                <label className="flex items-center gap-2 text-sm text-ink"><input type="checkbox" checked={!!form.data.verify_tls} onChange={(e) => form.setData('verify_tls', e.target.checked)} className="rounded border-edge bg-surface-sunken text-brand" />Verify TLS certificate</label>
                <label className="flex items-center gap-2 text-sm text-ink"><input type="checkbox" checked={!!form.data.is_maintenance} onChange={(e) => form.setData('is_maintenance', e.target.checked)} className="rounded border-edge bg-surface-sunken text-brand" />Maintenance mode</label>
                <label className="flex items-center gap-2 text-sm text-ink"><input type="checkbox" checked={!!form.data.is_deployable} onChange={(e) => form.setData('is_deployable', e.target.checked)} className="rounded border-edge bg-surface-sunken text-brand" />Available for new deployments</label>
                <div className="md:col-span-2">
                    <label className="hv-label">Notes</label>
                    <textarea rows={3} className="hv-input" value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
                </div>
                <div className="md:col-span-2"><button className="hv-btn-primary" disabled={form.processing}>Save node</button></div>
            </form>
        </Card>
    );
}
