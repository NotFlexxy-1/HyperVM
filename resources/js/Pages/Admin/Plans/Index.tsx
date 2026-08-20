import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Card } from '@/Components/UI';
import { megabytes } from '@/lib/format';

export default function PlansIndex({ plans }: any) {
    const form = useForm<any>({
        name: '', description: '', cpu_cores: 2, memory_mb: 2048, disk_mb: 20480, bandwidth_gb: 1000,
        network_speed_mbps: 1000, snapshot_limit: 2, backup_limit: 2, allocation_limit: 1,
        monthly_price: 0, currency: 'USD', is_public: true, sort_order: 0,
    });

    return (
        <AppLayout title="Plans">
            <Head title="Plans" />
            <div className="grid gap-6 lg:grid-cols-3">
                <Card title="New plan">
                    <form className="space-y-3" onSubmit={(e) => { e.preventDefault(); form.post('/admin/plans', { onSuccess: () => form.reset() }); }}>
                        {(['name', 'cpu_cores', 'memory_mb', 'disk_mb', 'bandwidth_gb', 'monthly_price'] as const).map((f) => (
                            <div key={f}>
                                <label className="hv-label">{f.replace('_', ' ')}</label>
                                <input className="hv-input" value={form.data[f]} onChange={(e) => form.setData(f, f === 'name' ? e.target.value : Number(e.target.value))} />
                                {form.errors[f] && <p className="mt-1 text-xs text-red-400">{form.errors[f]}</p>}
                            </div>
                        ))}
                        <button className="hv-btn-primary w-full" disabled={form.processing}>Create plan</button>
                    </form>
                </Card>

                <Card title="Available plans" className="lg:col-span-2">
                    <div className="grid gap-4 md:grid-cols-2">
                        {plans.map((p: any) => (
                            <div key={p.id} className="hv-card hv-density">
                                <div className="flex items-center justify-between">
                                    <p className="font-semibold text-ink">{p.name}</p>
                                    <span className="text-sm text-brand">{p.monthly_price} {p.currency}</span>
                                </div>
                                <p className="mt-2 text-xs text-ink-muted">{p.cpu_cores} vCPU · {megabytes(p.memory_mb)} RAM · {megabytes(p.disk_mb)} disk</p>
                                <p className="mt-1 text-xs text-ink-muted">{p.servers_count} servers using this plan</p>
                                <button className="mt-3 text-xs text-red-400" onClick={() => router.delete(`/admin/plans/${p.id}`)}>Delete</button>
                            </div>
                        ))}
                    </div>
                </Card>
            </div>
        </AppLayout>
    );
}
