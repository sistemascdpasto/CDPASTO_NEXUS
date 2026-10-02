import { BrandMark } from '@/components/app-logo';
import { statusColor, statusLabel } from '@/components/status-badge';
import { HealthRing, Sparkline } from '@/components/viz';
import { formatMs, formatPercent, formatRelative } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type AppStatus } from '@/types';
import { Head, Link, usePoll } from '@inertiajs/react';
import { AlertOctagon, ArrowLeft, Bug, Construction, Expand, KeyRound, Rocket, ScrollText, Shrink, Siren, UsersRound } from 'lucide-react';
import { AnimatePresence, motion } from 'motion/react';
import { useEffect, useState } from 'react';

interface System {
    id: number;
    name: string;
    type: string;
    status: AppStatus;
    response_ms: number | null;
    uptime_24h: number | null;
    score: number | null;
    active_users: number;
    errors_today: number;
    latency: { ms: number | null; status: AppStatus }[];
    incident_since: string | null;
    last_deploy: { status: string; at: string; message: string | null } | null;
    maintenance: boolean;
}

interface FeedItem {
    id: string;
    kind: 'audit' | 'login' | 'error' | 'alert';
    action: string;
    app: string | null;
    title: string;
    subtitle: string;
    at: string;
}

const kindIcon = { audit: ScrollText, login: KeyRound, error: Bug, alert: AlertOctagon };

function feedTone(item: FeedItem): string {
    if (item.kind === 'error' || item.action === 'failed' || item.action === 'deleted' || item.action === 'critical') return 'var(--status-critical)';
    if (item.kind === 'alert' || item.action === 'lockout') return 'var(--status-warning)';
    if (item.kind === 'login') return 'var(--status-good)';

    return 'var(--series-1)';
}

export default function Noc({ systems, feed, refreshedAt }: { systems: System[]; feed: FeedItem[]; refreshedAt: string }) {
    usePoll(15_000, {}, { keepAlive: true });
    const [now, setNow] = useState(() => new Date());
    const [fullscreen, setFullscreen] = useState(false);

    useEffect(() => {
        const t = setInterval(() => setNow(new Date()), 1000);
        const onChange = () => setFullscreen(Boolean(document.fullscreenElement));
        document.addEventListener('fullscreenchange', onChange);

        return () => {
            clearInterval(t);
            document.removeEventListener('fullscreenchange', onChange);
        };
    }, []);

    const down = systems.filter((s) => s.status === 'down').length;
    const degraded = systems.filter((s) => s.status === 'degraded').length;
    const tone = down ? 'var(--status-critical)' : degraded ? 'var(--status-warning)' : 'var(--status-good)';
    const headline = down
        ? `${down} sistema${down > 1 ? 's' : ''} caído${down > 1 ? 's' : ''}`
        : degraded
          ? `${degraded} con degradación`
          : 'Todos los sistemas operativos';
    const users = systems.reduce((sum, s) => sum + s.active_users, 0);
    const errors = systems.reduce((sum, s) => sum + s.errors_today, 0);

    return (
        <>
            <Head title="Centro de operaciones" />
            <div className="dark bg-background text-foreground flex min-h-screen flex-col">
                <div className="bg-grid pointer-events-none fixed inset-0 opacity-40 [mask-image:radial-gradient(ellipse_at_top,black,transparent_70%)]" />

                <header className="relative flex flex-wrap items-center gap-4 border-b px-6 py-4">
                    <Link href="/dashboard" className="text-muted-foreground hover:text-foreground" aria-label="Volver al panel">
                        <ArrowLeft className="size-5" />
                    </Link>
                    <BrandMark className="size-9" />
                    <div className="leading-tight">
                        <p className="font-semibold tracking-wide">Centro de operaciones</p>
                        <p className="text-muted-foreground text-xs">Nexus · CD Pasto · actualizado {formatRelative(refreshedAt)}</p>
                    </div>

                    <div className="ml-auto flex items-center gap-6">
                        <div className="flex items-center gap-2 rounded-full border px-4 py-1.5 text-sm font-medium" style={{ borderColor: tone }}>
                            <span className="relative flex size-2.5">
                                <span className="absolute inline-flex size-full animate-ping rounded-full opacity-70" style={{ background: tone }} />
                                <span className="relative inline-flex size-2.5 rounded-full" style={{ background: tone }} />
                            </span>
                            {headline}
                        </div>
                        <Stat icon={UsersRound} label="Conectados" value={users} />
                        <Stat icon={Bug} label="Errores hoy" value={errors} />
                        <div className="text-right tabular-nums">
                            <div className="font-mono text-2xl font-semibold">
                                {now.toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
                            </div>
                            <div className="text-muted-foreground text-xs capitalize">
                                {now.toLocaleDateString('es-CO', { weekday: 'long', day: 'numeric', month: 'long' })}
                            </div>
                        </div>
                        <button
                            onClick={() => (document.fullscreenElement ? document.exitFullscreen() : document.documentElement.requestFullscreen())}
                            className="hover:bg-accent rounded-lg border p-2"
                            aria-label={fullscreen ? 'Salir de pantalla completa' : 'Pantalla completa'}
                        >
                            {fullscreen ? <Shrink className="size-5" /> : <Expand className="size-5" />}
                        </button>
                    </div>
                </header>

                <main className="relative grid flex-1 gap-5 p-6 xl:grid-cols-[1fr_380px]">
                    <div className="grid content-start gap-5 md:grid-cols-2 2xl:grid-cols-3">
                        {systems.map((s) => (
                            <SystemTile key={s.id} system={s} />
                        ))}
                    </div>

                    <aside className="bg-card/60 flex max-h-[calc(100vh-7.5rem)] flex-col overflow-hidden rounded-2xl border backdrop-blur">
                        <div className="flex items-center justify-between border-b px-4 py-3">
                            <h2 className="text-sm font-semibold">Actividad en vivo</h2>
                            <span className="text-muted-foreground inline-flex items-center gap-1.5 text-xs">
                                <span className="size-1.5 animate-pulse rounded-full bg-[var(--status-good)]" /> en directo
                            </span>
                        </div>
                        <ul className="flex-1 space-y-1 overflow-hidden p-2">
                            <AnimatePresence initial={false}>
                                {feed.map((item) => {
                                    const Icon = kindIcon[item.kind];

                                    return (
                                        <motion.li
                                            key={item.id}
                                            layout
                                            initial={{ opacity: 0, x: 24, scale: 0.97 }}
                                            animate={{ opacity: 1, x: 0, scale: 1 }}
                                            exit={{ opacity: 0 }}
                                            transition={{ type: 'spring', stiffness: 260, damping: 26 }}
                                            className="flex gap-3 rounded-lg px-2 py-2"
                                        >
                                            <span
                                                className="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-md"
                                                style={{ background: `color-mix(in oklab, ${feedTone(item)} 18%, transparent)` }}
                                            >
                                                <Icon className="size-3.5" style={{ color: feedTone(item) }} />
                                            </span>
                                            <div className="min-w-0 text-sm">
                                                <p className="truncate">{item.title}</p>
                                                <p className="text-muted-foreground truncate text-xs">
                                                    {item.app} · {formatRelative(item.at)}
                                                </p>
                                            </div>
                                        </motion.li>
                                    );
                                })}
                            </AnimatePresence>
                            {feed.length === 0 && <li className="text-muted-foreground p-6 text-center text-sm">Sin actividad reciente</li>}
                        </ul>
                    </aside>
                </main>
            </div>
        </>
    );
}

function Stat({ icon: Icon, label, value }: { icon: typeof Bug; label: string; value: number }) {
    return (
        <div className="hidden items-center gap-2 lg:flex">
            <Icon className="text-muted-foreground size-5" />
            <div className="leading-tight">
                <div className="text-xl font-semibold tabular-nums">{value}</div>
                <div className="text-muted-foreground text-[11px]">{label}</div>
            </div>
        </div>
    );
}

function SystemTile({ system: s }: { system: System }) {
    const color = statusColor(s.status);
    const isDown = s.status === 'down';

    return (
        <Link
            href={`/applications/${s.id}`}
            className={cn(
                'bg-card/70 group relative overflow-hidden rounded-2xl border p-5 backdrop-blur transition-transform hover:-translate-y-0.5',
                isDown && 'ring-2 ring-[var(--status-critical)]',
            )}
        >
            <div className="absolute inset-x-0 top-0 h-1" style={{ background: color }} />
            {isDown && <div className="pointer-events-none absolute inset-0 animate-pulse bg-[var(--status-critical)] opacity-[0.07]" />}

            <div className="flex items-start gap-4">
                <HealthRing score={s.score} size={64} stroke={6} />
                <div className="min-w-0 flex-1">
                    <h3 className="truncate text-lg font-semibold">{s.name}</h3>
                    <p className="mt-0.5 inline-flex items-center gap-1.5 text-sm">
                        <span className="size-2 rounded-full" style={{ background: color }} />
                        {statusLabel(s.status)}
                        {s.maintenance && (
                            <span className="ml-1 inline-flex items-center gap-1 text-[var(--status-warning)]">
                                <Construction className="size-3.5" /> mantenimiento
                            </span>
                        )}
                    </p>
                    {s.incident_since && (
                        <p className="mt-1 inline-flex items-center gap-1 text-xs text-[var(--status-critical)]">
                            <Siren className="size-3.5" /> Caída desde {formatRelative(s.incident_since)}
                        </p>
                    )}
                </div>
                <div className="text-right">
                    <div className="font-mono text-2xl font-semibold tabular-nums">{formatMs(s.response_ms)}</div>
                    <div className="text-muted-foreground text-[11px]">respuesta</div>
                </div>
            </div>

            <div className="mt-4">
                <Sparkline
                    values={s.latency.map((l) => l.ms)}
                    failures={s.latency.map((l) => l.status === 'down')}
                    height={44}
                    color={isDown ? 'var(--status-critical)' : 'var(--series-1)'}
                />
                <p className="text-muted-foreground mt-1 text-[10px]">Latencia de las últimas verificaciones</p>
            </div>

            <dl className="mt-4 grid grid-cols-3 gap-2 border-t pt-3 text-center">
                <div>
                    <dt className="text-muted-foreground text-[11px]">Uptime 24 h</dt>
                    <dd className="font-semibold tabular-nums">{formatPercent(s.uptime_24h, 1)}</dd>
                </div>
                <div>
                    <dt className="text-muted-foreground text-[11px]">Conectados</dt>
                    <dd className="font-semibold tabular-nums">{s.type === 'static' ? '—' : s.active_users}</dd>
                </div>
                <div>
                    <dt className="text-muted-foreground text-[11px]">Errores hoy</dt>
                    <dd className={cn('font-semibold tabular-nums', s.errors_today > 0 && 'text-[var(--status-serious)]')}>
                        {s.type === 'static' ? '—' : s.errors_today}
                    </dd>
                </div>
            </dl>

            {s.last_deploy && (
                <p className="text-muted-foreground mt-3 flex items-center gap-1.5 truncate text-[11px]">
                    <Rocket className="size-3" /> {formatRelative(s.last_deploy.at)} · {s.last_deploy.message ?? s.last_deploy.status}
                </p>
            )}
        </Link>
    );
}
