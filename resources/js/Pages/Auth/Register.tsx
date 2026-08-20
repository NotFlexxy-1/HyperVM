import { Head, Link, useForm } from '@inertiajs/react';
import AuthLayout from '@/Layouts/AuthLayout';

export default function Register() {
    const form = useForm({ name: '', username: '', email: '', password: '', password_confirmation: '' });

    return (
        <AuthLayout title="Create account" subtitle="Registration is enabled by the panel administrator">
            <Head title="Register" />
            <form onSubmit={(e) => { e.preventDefault(); form.post('/register'); }} className="space-y-4">
                {(['name', 'username', 'email'] as const).map((field) => (
                    <div key={field}>
                        <label className="hv-label capitalize" htmlFor={field}>{field}</label>
                        <input id={field} className="hv-input" value={form.data[field]}
                            onChange={(e) => form.setData(field, e.target.value)} required />
                        {form.errors[field] && <p className="mt-1 text-xs text-red-400">{form.errors[field]}</p>}
                    </div>
                ))}
                <div>
                    <label className="hv-label" htmlFor="password">Password</label>
                    <input id="password" type="password" className="hv-input" value={form.data.password}
                        onChange={(e) => form.setData('password', e.target.value)} required />
                    {form.errors.password && <p className="mt-1 text-xs text-red-400">{form.errors.password}</p>}
                </div>
                <div>
                    <label className="hv-label" htmlFor="password_confirmation">Confirm password</label>
                    <input id="password_confirmation" type="password" className="hv-input" value={form.data.password_confirmation}
                        onChange={(e) => form.setData('password_confirmation', e.target.value)} required />
                </div>
                <button className="hv-btn-primary w-full" disabled={form.processing}>Create account</button>
            </form>
            <p className="mt-6 text-xs text-ink-muted">Already registered? <Link href="/login" className="hover:text-brand">Sign in</Link></p>
        </AuthLayout>
    );
}
