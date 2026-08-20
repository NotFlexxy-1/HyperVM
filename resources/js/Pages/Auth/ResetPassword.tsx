import { Head, useForm } from '@inertiajs/react';
import AuthLayout from '@/Layouts/AuthLayout';

export default function ResetPassword({ token, email }: { token: string; email: string }) {
    const form = useForm({ token, email: email ?? '', password: '', password_confirmation: '' });

    return (
        <AuthLayout title="Choose a new password">
            <Head title="New password" />
            <form onSubmit={(e) => { e.preventDefault(); form.post('/reset-password'); }} className="space-y-4">
                <input className="hv-input" type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} required />
                <input className="hv-input" type="password" placeholder="New password" value={form.data.password}
                    onChange={(e) => form.setData('password', e.target.value)} required />
                {form.errors.password && <p className="text-xs text-red-400">{form.errors.password}</p>}
                <input className="hv-input" type="password" placeholder="Confirm password" value={form.data.password_confirmation}
                    onChange={(e) => form.setData('password_confirmation', e.target.value)} required />
                <button className="hv-btn-primary w-full" disabled={form.processing}>Update password</button>
            </form>
        </AuthLayout>
    );
}
