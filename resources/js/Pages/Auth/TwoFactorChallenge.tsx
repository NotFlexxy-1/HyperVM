import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AuthLayout from '@/Layouts/AuthLayout';
import type { PageProps } from '@/types';

export default function TwoFactorChallenge() {
    const { settings, flash } = usePage<PageProps>().props;
    const [useRecovery, setUseRecovery] = useState(false);
    const form = useForm({ code: '', recovery_code: '' });

    return (
        <AuthLayout
            title="Two-factor authentication"
            subtitle={`Confirm it is you to finish signing in to ${settings.branding.panel_name}`}
        >
            <Head title="Two-factor authentication" />
            {flash.error && <p className="mb-4 rounded-panel border border-red-500/40 px-3 py-2 text-sm text-red-400">{flash.error}</p>}

            <form
                className="space-y-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/two-factor');
                }}
            >
                {useRecovery ? (
                    <div>
                        <label className="hv-label" htmlFor="recovery_code">Recovery code</label>
                        <input
                            id="recovery_code"
                            className="hv-input font-mono"
                            autoComplete="one-time-code"
                            value={form.data.recovery_code}
                            onChange={(e) => form.setData('recovery_code', e.target.value)}
                            required
                        />
                        {form.errors.recovery_code && <p className="mt-1 text-xs text-red-400">{form.errors.recovery_code}</p>}
                    </div>
                ) : (
                    <div>
                        <label className="hv-label" htmlFor="code">Authentication code</label>
                        <input
                            id="code"
                            className="hv-input text-center font-mono text-lg tracking-[0.5em]"
                            inputMode="numeric"
                            maxLength={6}
                            autoFocus
                            autoComplete="one-time-code"
                            value={form.data.code}
                            onChange={(e) => form.setData('code', e.target.value.replace(/\D/g, ''))}
                            required
                        />
                        {form.errors.code && <p className="mt-1 text-xs text-red-400">{form.errors.code}</p>}
                    </div>
                )}

                <button type="submit" className="hv-btn-primary w-full" disabled={form.processing}>
                    Verify and continue
                </button>
            </form>

            <div className="mt-6 flex justify-between text-xs text-ink-muted">
                <button
                    type="button"
                    className="hover:text-brand"
                    onClick={() => {
                        form.reset();
                        setUseRecovery((v) => !v);
                    }}
                >
                    {useRecovery ? 'Use an authenticator code' : 'Use a recovery code'}
                </button>
                <Link href="/login" className="hover:text-brand">Back to sign in</Link>
            </div>
        </AuthLayout>
    );
}
