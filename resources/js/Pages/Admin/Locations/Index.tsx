import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Card } from '@/Components/UI';

export default function LocationsIndex({ locations }: any) {
    const form = useForm({ short_code: '', name: '', country_code: '', description: '' });

    return (
        <AppLayout title="Locations">
            <Head title="Locations" />
            <div className="grid gap-6 lg:grid-cols-2">
                <Card title="Add location">
                    <form className="space-y-3" onSubmit={(e) => { e.preventDefault(); form.post('/admin/locations', { onSuccess: () => form.reset() }); }}>
                        {(['short_code', 'name', 'country_code', 'description'] as const).map((f) => (
                            <div key={f}>
                                <label className="hv-label">{f.replace('_', ' ')}</label>
                                <input className="hv-input" value={form.data[f]} onChange={(e) => form.setData(f, e.target.value)} />
                                {form.errors[f] && <p className="mt-1 text-xs text-red-400">{form.errors[f]}</p>}
                            </div>
                        ))}
                        <button className="hv-btn-primary w-full" disabled={form.processing}>Create</button>
                    </form>
                </Card>
                <Card title="Locations">
                    <ul className="divide-y divide-edge/50 text-sm">
                        {locations.map((l: any) => (
                            <li key={l.id} className="flex items-center justify-between py-2">
                                <span className="text-ink">{l.short_code} — {l.name}</span>
                                <span className="text-xs text-ink-muted">{l.nodes_count} nodes
                                    <button className="ml-3 text-red-400" onClick={() => router.delete(`/admin/locations/${l.id}`)}>delete</button>
                                </span>
                            </li>
                        ))}
                    </ul>
                </Card>
            </div>
        </AppLayout>
    );
}
