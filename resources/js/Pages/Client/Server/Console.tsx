import { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { ExternalLink, Keyboard, Maximize2, RefreshCw, SquareTerminal } from 'lucide-react';
import ServerLayout from '@/Layouts/ServerLayout';
import { Alert, Card, Spinner } from '@/Components/UI';

interface Ticket {
    ticket: string | null;
    port: string | number | null;
    user: string | null;
    node: string;
    host: string;
    api_port: number;
    vmid: number;
}

/**
 * noVNC client wired to the Proxmox `vncwebsocket` endpoint using the
 * single-use ticket minted server-side by ServerService::consoleTicket().
 */
export default function Console({ server, permissions, ticket, error }: any) {
    const screen = useRef<HTMLDivElement>(null);
    const rfb = useRef<any>(null);
    const [current, setCurrent] = useState<Ticket | null>(ticket ?? null);
    const [state, setState] = useState<'idle' | 'connecting' | 'connected' | 'failed'>(ticket ? 'connecting' : 'idle');
    const [message, setMessage] = useState<string | null>(error ?? null);

    const wsUrl = (t: Ticket) =>
        `wss://${t.host}:${t.api_port}/api2/json/nodes/${t.node}/qemu/${t.vmid}/vncwebsocket?port=${t.port}&vncticket=${encodeURIComponent(
            t.ticket ?? '',
        )}`;

    useEffect(() => {
        if (!current?.ticket || !screen.current) return;
        let disposed = false;

        (async () => {
            try {
                const { default: RFB } = await import('@novnc/novnc');
                if (disposed || !screen.current) return;
                screen.current.innerHTML = '';
                const client = new RFB(screen.current, wsUrl(current), {
                    credentials: { password: current.ticket ?? '' },
                    wsProtocols: ['binary'],
                });
                client.scaleViewport = true;
                client.clipViewport = true;
                client.addEventListener('connect', () => setState('connected'));
                client.addEventListener('disconnect', (e: any) => {
                    setState(e?.detail?.clean ? 'idle' : 'failed');
                    if (!e?.detail?.clean) setMessage('The console connection was closed by the hypervisor. Request a new session.');
                });
                client.addEventListener('securityfailure', () => {
                    setState('failed');
                    setMessage('The VNC ticket was rejected. Request a new session.');
                });
                rfb.current = client;
            } catch {
                setState('failed');
                setMessage('The noVNC client failed to load. Run `npm install` and rebuild the frontend assets.');
            }
        })();

        return () => {
            disposed = true;
            try {
                rfb.current?.disconnect();
            } catch {
                /* already closed */
            }
            rfb.current = null;
        };
    }, [current]);

    const reconnect = async () => {
        setState('connecting');
        setMessage(null);
        try {
            const { data } = await axios.get(`/servers/${server.uuid_short}/console/ticket`);
            setCurrent(data);
        } catch (e: any) {
            setState('failed');
            setMessage(e?.response?.data?.error ?? 'Unable to issue a console ticket.');
        }
    };

    const sendCtrlAltDel = () => rfb.current?.sendCtrlAltDel();
    const fullscreen = () => screen.current?.parentElement?.requestFullscreen?.();

    return (
        <ServerLayout server={server} permissions={permissions} state={state === 'connected' ? 'running' : undefined}>
            <div className="mt-6 space-y-6">
                {message && <Alert tone="error">{message}</Alert>}

                <Card
                    title="VNC console"
                    subtitle={current ? `${current.node} · VMID ${current.vmid}` : undefined}
                    bodyClassName=""
                    action={
                        <div className="flex flex-wrap items-center gap-2">
                            <button className="hv-btn-ghost py-1.5" onClick={sendCtrlAltDel} disabled={state !== 'connected'}>
                                <Keyboard className="h-4 w-4" /> Ctrl+Alt+Del
                            </button>
                            <button className="hv-btn-ghost py-1.5" onClick={fullscreen} disabled={state !== 'connected'}>
                                <Maximize2 className="h-4 w-4" /> Fullscreen
                            </button>
                            <button className="hv-btn-primary py-1.5" onClick={reconnect}>
                                <RefreshCw className={`h-4 w-4 ${state === 'connecting' ? 'animate-spin' : ''}`} /> New session
                            </button>
                        </div>
                    }
                >
                    <div className="relative aspect-[16/9] w-full overflow-hidden rounded-b-panel bg-black">
                        <div ref={screen} className="h-full w-full" />
                        {state !== 'connected' && (
                            <div className="absolute inset-0 grid place-items-center gap-3 bg-black/70 text-center text-sm text-slate-300">
                                <div className="flex flex-col items-center gap-3">
                                    {state === 'connecting' ? <Spinner className="h-6 w-6" /> : <SquareTerminal className="h-8 w-8" />}
                                    <p>
                                        {state === 'connecting'
                                            ? 'Negotiating VNC session…'
                                            : state === 'failed'
                                              ? 'Console disconnected.'
                                              : 'No active console session.'}
                                    </p>
                                    {state !== 'connecting' && (
                                        <button className="hv-btn-primary" onClick={reconnect}>
                                            Start console
                                        </button>
                                    )}
                                </div>
                            </div>
                        )}
                    </div>
                </Card>

                <Card title="Troubleshooting">
                    <ul className="list-inside list-disc space-y-1.5 text-sm text-ink-muted">
                        <li>Your browser must trust the Proxmox node&apos;s TLS certificate on port {current?.api_port ?? 8006}; self-signed certificates must be accepted once.</li>
                        <li>Console tickets are single-use and expire quickly — press “New session” if the screen stays black.</li>
                        <li>The VM must be running for the framebuffer to render output.</li>
                    </ul>
                    {current && (
                        <a
                            className="hv-btn-ghost mt-4"
                            href={`https://${current.host}:${current.api_port}/?console=kvm&novnc=1&vmid=${current.vmid}&node=${current.node}`}
                            target="_blank"
                            rel="noreferrer"
                        >
                            <ExternalLink className="h-4 w-4" /> Open the hypervisor console
                        </a>
                    )}
                </Card>
            </div>
        </ServerLayout>
    );
}
