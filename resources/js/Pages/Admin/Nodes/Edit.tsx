import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import NodeForm from './Form';

export default function EditNode({ node, locations }: any) {
    return (
        <AppLayout title={`Edit ${node.name}`}>
            <Head title={`Edit ${node.name}`} />
            <NodeForm node={node} locations={locations} method="patch" action={`/admin/nodes/${node.id}`} />
        </AppLayout>
    );
}
