import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AuthLayout from '@/Layouts/AuthLayout';
import type { PageProps } from '@/types';

export default function Login() {
    const { settings, flash } = usePage<PageProps>().props;
    const form = useForm({ identifier: '', password: '', remember: false });

    return (
        <AuthLayout title="Sign in" subtitle={`Access your ${settings.branding.panel_name} account`}>
            <Head title="Sign in" />
            {flash.error && <p className="mb-4 rounded-panel border border-red-500/40 px-3 py-2 text-sm text-red-400">{flash.error}</p>}

            <form onSubmit={(e) => { e.preventDefault(); form.post('/login'); }} className="space-y-4">
                <div>
                    <label className="hv-label" htmlFor="identifier">Email or username</label>
                    <input id="identifier" className="hv-input" value={form.data.identifier}
                        onChange={(e) => form.setData('identifier', e.target.value)} autoComplete="username" required />
                    {form.errors.identifier && <p className="mt-1 text-xs text-red-400">{form.errors.identifier}</p>}
                </div>
                <div>
                    <label className="hv-label" htmlFor="password">Password</label>
                    <input id="password" type="password" className="hv-input" value={form.data.password}
                        onChange={(e) => form.setData('password', e.target.value)} autoComplete="current-password" required />
                </div>
                <label className="flex items-center gap-2 text-sm text-ink-muted">
                    <input type="checkbox" className="rounded border-edge bg-surface-sunken text-brand focus:ring-brand"
                        checked={form.data.remember} onChange={(e) => form.setData('remember', e.target.checked)} />
                    Keep me signed in
                </label>
                <button type="submit" className="hv-btn-primary w-full" disabled={form.processing}>Sign in</button>
            </form>

            {settings.registration.discord_enabled && (
                <a href="/auth/discord/redirect" className="hv-btn-ghost mt-3 w-full">Continue with Discord</a>
            )}

            <div className="mt-6 flex justify-between text-xs text-ink-muted">
                <Link href="/forgot-password" className="hover:text-brand">Forgot password?</Link>
                {settings.registration.enabled && <Link href="/register" className="hover:text-brand">Create an account</Link>}
            </div>
        </AuthLayout>
    );
}
