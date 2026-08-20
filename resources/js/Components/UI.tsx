import { AlertTriangle, CheckCircle2, ChevronDown, Loader2, X } from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';
import { percent } from '@/lib/format';

/* ------------------------------------------------------------------ */
/* Surfaces                                                            */
/* ------------------------------------------------------------------ */

export const Card = ({
    title,
    subtitle,
    action,
    children,
    className = '',
    bodyClassName = 'hv-density',
}: {
    title?: ReactNode;
    subtitle?: ReactNode;
    action?: ReactNode;
    children: ReactNode;
    className?: string;
    bodyClassName?: string;
}) => (
    <section className={`hv-card animate-fade-up ${className}`}>
        {(title || action) && (
            <header className="flex flex-wrap items-center justify-between gap-3 border-b border-edge/60 px-5 py-3.5">
                <div className="min-w-0">
                    {title && <h2 className="truncate text-sm font-semibold tracking-wide text-ink">{title}</h2>}
                    {subtitle && <p className="mt-0.5 truncate text-xs text-ink-muted">{subtitle}</p>}
                </div>
                {action}
            </header>
        )}
        <div className={bodyClassName}>{children}</div>
    </section>
);

export const Stat = ({
    label,
    value,
    hint,
    icon,
}: {
    label: string;
    value: ReactNode;
    hint?: ReactNode;
    icon?: ReactNode;
}) => (
    <div className="hv-card hv-density relative overflow-hidden">
        <div className="flex items-start justify-between gap-3">
            <div className="min-w-0">
                <p className="text-xs font-semibold uppercase tracking-wider text-ink-muted">{label}</p>
                <p className="mt-2 truncate text-3xl font-bold text-ink">{value}</p>
                {hint && <p className="mt-1 text-xs text-ink-muted">{hint}</p>}
            </div>
            {icon && <span className="grid h-10 w-10 shrink-0 place-items-center rounded-panel bg-brand/12 text-brand">{icon}</span>}
        </div>
    </div>
);

export const Meter = ({
    label,
    used,
    total,
    unit = '',
    format,
}: {
    label: string;
    used: number;
    total: number;
    unit?: string;
    format?: (v: number) => string;
}) => {
    const pct = percent(used, total);
    const render = (v: number) => (format ? format(v) : `${v}${unit}`);
    return (
        <div>
            <div className="mb-1.5 flex justify-between text-xs text-ink-muted">
                <span>{label}</span>
                <span className="tabular-nums">
                    {render(used)} / {render(total)}
                </span>
            </div>
            <div className="h-2 overflow-hidden rounded-full bg-surface-sunken">
                <div
                    className={`h-full rounded-full transition-all duration-500 ${
                        pct > 90 ? 'bg-red-500' : pct > 75 ? 'bg-amber-500' : 'bg-gradient-to-r from-brand to-accent'
                    }`}
                    style={{ width: `${pct}%` }}
                />
            </div>
        </div>
    );
};

export const Badge = ({ tone = 'default', children }: { tone?: 'default' | 'ok' | 'warn' | 'bad' | 'brand'; children: ReactNode }) => {
    const tones = {
        default: 'border-edge text-ink-muted',
        ok: 'border-emerald-500/40 bg-emerald-500/10 text-emerald-400',
        warn: 'border-amber-500/40 bg-amber-500/10 text-amber-400',
        bad: 'border-red-500/40 bg-red-500/10 text-red-400',
        brand: 'border-brand/40 bg-brand/10 text-brand',
    } as const;
    return <span className={`hv-chip ${tones[tone]}`}>{children}</span>;
};

export const StatusDot = ({ state }: { state: string }) => {
    const tone =
        state === 'running' ? 'bg-emerald-400' : state === 'paused' || state === 'suspended' ? 'bg-amber-400' : state === 'unknown' ? 'bg-slate-400' : 'bg-red-400';
    return (
        <span className="relative flex h-2.5 w-2.5">
            {state === 'running' && <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400/70" />}
            <span className={`relative inline-flex h-2.5 w-2.5 rounded-full ${tone}`} />
        </span>
    );
};

/* ------------------------------------------------------------------ */
/* Feedback                                                            */
/* ------------------------------------------------------------------ */

export const Alert = ({ tone = 'info', children }: { tone?: 'info' | 'error' | 'success' | 'warn'; children: ReactNode }) => {
    const tones = {
        info: 'border-brand/40 bg-brand/8 text-ink',
        error: 'border-red-500/40 bg-red-500/10 text-red-400',
        success: 'border-emerald-500/40 bg-emerald-500/10 text-emerald-400',
        warn: 'border-amber-500/40 bg-amber-500/10 text-amber-400',
    } as const;
    const Icon = tone === 'success' ? CheckCircle2 : AlertTriangle;
    return (
        <div className={`flex items-start gap-2.5 rounded-panel border px-4 py-3 text-sm ${tones[tone]}`}>
            <Icon className="mt-0.5 h-4 w-4 shrink-0" />
            <div className="min-w-0 flex-1 break-words">{children}</div>
        </div>
    );
};

export const Spinner = ({ className = 'h-4 w-4' }: { className?: string }) => <Loader2 className={`animate-spin ${className}`} />;

export const Skeleton = ({ className = 'h-4 w-full' }: { className?: string }) => (
    <div className={`animate-pulse rounded-panel bg-surface-sunken ${className}`} />
);

export const EmptyState = ({ icon, title, description, action }: { icon?: ReactNode; title: string; description?: string; action?: ReactNode }) => (
    <div className="flex flex-col items-center justify-center gap-3 px-6 py-14 text-center">
        {icon && <span className="grid h-14 w-14 place-items-center rounded-full bg-surface-sunken text-ink-muted">{icon}</span>}
        <p className="text-sm font-semibold text-ink">{title}</p>
        {description && <p className="max-w-sm text-sm text-ink-muted">{description}</p>}
        {action}
    </div>
);

/* ------------------------------------------------------------------ */
/* Forms                                                               */
/* ------------------------------------------------------------------ */

export const Field = ({
    label,
    error,
    hint,
    children,
    className = '',
}: {
    label?: string;
    error?: string;
    hint?: string;
    children: ReactNode;
    className?: string;
}) => (
    <div className={className}>
        {label && <label className="hv-label">{label}</label>}
        {children}
        {hint && !error && <p className="mt-1 text-xs text-ink-muted">{hint}</p>}
        {error && <p className="mt-1 text-xs font-medium text-red-400">{error}</p>}
    </div>
);

export const Toggle = ({ checked, onChange, label }: { checked: boolean; onChange: (v: boolean) => void; label?: string }) => (
    <button
        type="button"
        role="switch"
        aria-checked={checked}
        aria-label={label}
        onClick={() => onChange(!checked)}
        className={`relative h-6 w-11 shrink-0 rounded-full transition ${checked ? 'bg-brand' : 'bg-surface-sunken border border-edge'}`}
    >
        <span className={`absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-all ${checked ? 'left-[22px]' : 'left-0.5'}`} />
    </button>
);

/* ------------------------------------------------------------------ */
/* Data display                                                        */
/* ------------------------------------------------------------------ */

export const Table = ({ head, children, empty }: { head: ReactNode[]; children: ReactNode; empty?: ReactNode }) => (
    <div className="hv-scroll overflow-x-auto">
        <table className="w-full min-w-[560px] text-left text-sm">
            <thead>
                <tr className="border-b border-edge/60">
                    {head.map((h, i) => (
                        <th key={i} className="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-ink-muted">
                            {h}
                        </th>
                    ))}
                </tr>
            </thead>
            <tbody className="divide-y divide-edge/50">{children}</tbody>
        </table>
        {empty}
    </div>
);

export const KeyValue = ({ items }: { items: Array<[ReactNode, ReactNode]> }) => (
    <dl className="grid gap-3 sm:grid-cols-2">
        {items.map(([k, v], i) => (
            <div key={i} className="rounded-panel border border-edge/60 bg-surface-sunken/50 px-3 py-2">
                <dt className="text-xs uppercase tracking-wider text-ink-muted">{k}</dt>
                <dd className="mt-0.5 break-all text-sm font-medium text-ink">{v}</dd>
            </div>
        ))}
    </dl>
);

/* ------------------------------------------------------------------ */
/* Overlays                                                            */
/* ------------------------------------------------------------------ */

export const Modal = ({
    open,
    onClose,
    title,
    children,
    footer,
    width = 'max-w-lg',
}: {
    open: boolean;
    onClose: () => void;
    title: string;
    children: ReactNode;
    footer?: ReactNode;
    width?: string;
}) => {
    useEffect(() => {
        if (!open) return;
        const onKey = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
        document.addEventListener('keydown', onKey);
        document.body.style.overflow = 'hidden';
        return () => {
            document.removeEventListener('keydown', onKey);
            document.body.style.overflow = '';
        };
    }, [open, onClose]);

    if (!open) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/60 p-0 backdrop-blur-sm sm:items-center sm:p-6" onMouseDown={onClose}>
            <div
                className={`hv-card animate-fade-up w-full ${width} max-h-[92vh] overflow-y-auto rounded-b-none sm:rounded-panel`}
                onMouseDown={(e) => e.stopPropagation()}
                role="dialog"
                aria-modal="true"
            >
                <header className="flex items-center justify-between border-b border-edge/60 px-5 py-3.5">
                    <h3 className="text-sm font-semibold text-ink">{title}</h3>
                    <button type="button" className="rounded-panel p-1 text-ink-muted transition hover:bg-surface-sunken hover:text-ink" onClick={onClose}>
                        <X className="h-4 w-4" />
                    </button>
                </header>
                <div className="hv-density">{children}</div>
                {footer && <footer className="flex justify-end gap-2 border-t border-edge/60 px-5 py-3.5">{footer}</footer>}
            </div>
        </div>
    );
};

export const ConfirmButton = ({
    onConfirm,
    children,
    title,
    body,
    confirmLabel = 'Confirm',
    className = 'hv-btn-danger',
    disabled,
}: {
    onConfirm: () => void;
    children: ReactNode;
    title: string;
    body: string;
    confirmLabel?: string;
    className?: string;
    disabled?: boolean;
}) => {
    const [open, setOpen] = useState(false);
    return (
        <>
            <button type="button" className={className} disabled={disabled} onClick={() => setOpen(true)}>
                {children}
            </button>
            <Modal
                open={open}
                onClose={() => setOpen(false)}
                title={title}
                footer={
                    <>
                        <button type="button" className="hv-btn-ghost" onClick={() => setOpen(false)}>
                            Cancel
                        </button>
                        <button
                            type="button"
                            className="hv-btn-danger"
                            onClick={() => {
                                setOpen(false);
                                onConfirm();
                            }}
                        >
                            {confirmLabel}
                        </button>
                    </>
                }
            >
                <p className="text-sm text-ink-muted">{body}</p>
            </Modal>
        </>
    );
};

export const Disclosure = ({ label, children, defaultOpen = false }: { label: ReactNode; children: ReactNode; defaultOpen?: boolean }) => {
    const [open, setOpen] = useState(defaultOpen);
    return (
        <div className="rounded-panel border border-edge/60">
            <button
                type="button"
                onClick={() => setOpen(!open)}
                className="flex w-full items-center justify-between gap-3 px-4 py-3 text-left text-sm font-semibold text-ink"
            >
                {label}
                <ChevronDown className={`h-4 w-4 text-ink-muted transition-transform ${open ? 'rotate-180' : ''}`} />
            </button>
            {open && <div className="border-t border-edge/60 px-4 py-3">{children}</div>}
        </div>
    );
};
