import { Head, useForm, usePage } from '@inertiajs/react';
import AuthLayout from '@/Layouts/AuthLayout';
import type { PageProps } from '@/types';

export default function ForgotPassword() {
    const { flash } = usePage<PageProps>().props;
    const form = useForm({ email: '' });

    return (
        <AuthLayout title="Reset password" subtitle="We'll email you a secure reset link">
            <Head title="Reset password" />
            {flash.success && <p className="mb-4 text-sm text-emerald-400">{flash.success}</p>}
            <form onSubmit={(e) => { e.preventDefault(); form.post('/forgot-password'); }} className="space-y-4">
                <input className="hv-input" type="email" value={form.data.email}
                    onChange={(e) => form.setData('email', e.target.value)} placeholder="you@example.com" required />
                {form.errors.email && <p className="text-xs text-red-400">{form.errors.email}</p>}
                <button className="hv-btn-primary w-full" disabled={form.processing}>Send reset link</button>
            </form>
        </AuthLayout>
    );
}
