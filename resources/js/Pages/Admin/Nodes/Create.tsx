import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import NodeForm from './Form';

export default function CreateNode({ locations }: any) {
    return (
        <AppLayout title="Add Proxmox node">
            <Head title="Add node" />
            <NodeForm node={null} locations={locations} method="post" action="/admin/nodes" />
        </AppLayout>
    );
}
