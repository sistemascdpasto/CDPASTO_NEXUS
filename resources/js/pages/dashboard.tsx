import { TimeChart } from '@/components/charts';
import { EmptyState, Panel } from '@/components/nexus-ui';
import SpotlightCard from '@/components/reactbits/SpotlightCard';
import { SeverityIcon, statusColor, statusLabel } from '@/components/status-badge';
import { AnimatedNumber, HealthRing, scoreLabel } from '@/components/viz';
import AppLayout from '@/layouts/app-layout';
import { formatMs, formatNumber, formatPercent, formatRelative } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type Alert, type AppStatus, type SharedData } from '@/types';
import { Head, Link, usePage, usePoll } from '@inertiajs/react';
import {
    Activity,
    ArrowRight,
    Bell,
    Bug,
    CheckCircle2,
    CircleDollarSign,
    Construction,
    FilePlus2,
    Gauge,
    KeyRound,
    ListRestart,
    Pencil,
    Radio,
    ScrollText,
    ShieldAlert,
    Siren,
    Trash2,
    UsersRound,
} from 'lucide-react';

interface DashboardApp {
    id: number;
    name: string;
    url: string;
    type: string;
    status: AppStatus;
    last_response_ms: number | null;
    last_checked_at: string | null;
    uptime_24h: number | null;
    active_users: number;
    errors_today: number;
    agent: boolean;
    findings: number;
    failed_jobs: number | null;
    maintenance: boolean;
    storage_percent: number | null;
    score: number | null;
    grade: string | null;
}

interface FeedItem {
    id: string;
    kind: string;
    action: string;
    app: string | null;
    title: string;
    subtitle: string;
    href: string;
    at: string;
}

interface Props {
    kpis: {
        apps_total: number;
        apps_online: number;
        apps_degraded: number;
        apps_down: number;
        active_users: number;
        errors_today: number;
        open_error_groups: number;
        failed_logins_today: number;
        uptime_avg: number | null;
        open_alerts: number;
    };
    applications: DashboardApp[];
    traffic: { time: string; requests: number; avg_ms: number; errors: number }[];
    recentAlerts: Alert[];
    findings: { key: string; label: string; detail: string; app: string; app_id: number }[];
    openIncidents: { id: number; app: string | null; started_at: string; cause: string | null }[];
    feed: FeedItem[];
    costs: { total_projected: number; total_to_date: number; month_progress: number } | null;
}

const usd = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'USD', minimumFractionDigits: 2 });

function greeting(): string {
    const hour = new Date().getHours();

    return hour < 12 ? 'Buenos días' : hour < 19 ? 'Buenas tardes' : 'Buenas noches';
}

function feedIcon(item: FeedItem): { icon: typeof Bug; color: string } {
    if (item.kind === 'error') return { icon: Bug, color: 'var(--status-critical)' };
    if (item.kind === 'login')
        return item.action === 'login' ? { icon: KeyRound, color: 'var(--status-good)' } : { icon: ShieldAlert, color: 'var(--status-critical)' };

    return (
        (
            {
                created: { icon: FilePlus2, color: 'var(--status-good)' },
                updated: { icon: Pencil, color: 'var(--series-1)' },
                deleted: { icon: Trash2, color: 'var(--status-critical)' },
            } as Record<string, { icon: typeof Bug; color: string }>
        )[item.action] ?? { icon: ScrollText, color: 'var(--series-2)' }
    );
}

export default function Dashboard({ kpis, applications, traffic, recentAlerts, findings, openIncidents, feed, costs }: Props) {
    const { auth } = usePage<SharedData>().props;
    usePoll(30_000, {}, { keepAlive: false });

    const attention = kpis.apps_down + kpis.apps_degraded;
    const tone = kpis.apps_down > 0 ? 'var(--status-critical)' : kpis.apps_degraded > 0 ? 'var(--status-warning)' : 'var(--status-good)';
    const scored = applications.filter((a) => a.score !== null);
    const avgScore = scored.length ? Math.round(scored.reduce((s, a) => s + (a.score ?? 0), 0) / scored.length) : null;

    return (
        <AppLayout breadcrumbs={[{ title: 'Resumen', href: '/dashboard' }]}>
            <Head title="Resumen" />
            <div className="flex flex-col gap-5 p-4 sm:p-6">
                {/* Encabezado con estado general */}
                <section className="relative overflow-hidden rounded-2xl border bg-[linear-gradient(120deg,hsl(226_70%_20%),hsl(224_50%_9%)_60%)] p-6 text-white sm:p-8">
                    <div className="bg-grid absolute inset-0 opacity-30 [mask-image:linear-gradient(to_left,black,transparent)]" />
                    <div className="absolute -top-20 -right-10 size-64 rounded-full bg-[radial-gradient(circle,rgba(245,179,1,.28),transparent_70%)]" />
                    <div className="relative flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <p className="text-sm text-white/70">
                                {greeting()}, {auth.user.name.split(' ')[0]}
                            </p>
                            <h1 className="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl">
                                {attention === 0 && kpis.apps_total > 0
                                    ? 'Todos los sistemas operan con normalidad'
                                    : `${attention} de ${kpis.apps_total} sistemas requieren atención`}
                            </h1>
                            <p className="mt-2 inline-flex items-center gap-2 text-sm text-white/70">
                                <span className="relative flex size-2">
                                    <span
                                        className="absolute inline-flex size-full animate-ping rounded-full opacity-70"
                                        style={{ background: tone }}
                                    />
                                    <span className="relative inline-flex size-2 rounded-full" style={{ background: tone }} />
                                </span>
                                {kpis.apps_online} en línea · {kpis.apps_degraded} degradadas · {kpis.apps_down} caídas
                            </p>
                        </div>
                        <div className="flex items-center gap-5">
                            <div className="rounded-xl bg-white/10 p-3 backdrop-blur">
                                <HealthRing score={avgScore} size={84} stroke={8} />
                            </div>
                            <div>
                                <p className="text-sm text-white/70">Salud promedio</p>
                                <p className="text-xl font-semibold">{scoreLabel(avgScore)}</p>
                                <Link
                                    href="/noc"
                                    className="mt-2 inline-flex items-center gap-1.5 text-sm font-medium text-[var(--brand-gold)] hover:underline"
                                >
                                    <Radio className="size-4" /> Modo TV
                                </Link>
                            </div>
                        </div>
                    </div>
                </section>

                {openIncidents.length > 0 && (
                    <Link
                        href="/incidents?status=open"
                        className="flex items-center gap-3 rounded-xl border-2 border-[var(--status-critical)] bg-[color-mix(in_oklab,var(--status-critical)_8%,transparent)] p-4"
                    >
                        <Siren className="size-5 shrink-0 animate-pulse text-[var(--status-critical)]" />
                        <div className="min-w-0 flex-1">
                            <p className="font-semibold">
                                {openIncidents.length === 1 ? `${openIncidents[0].app} está caída` : `${openIncidents.length} sistemas caídos`}
                            </p>
                            <p className="text-muted-foreground truncate text-sm">
                                Desde {formatRelative(openIncidents[0].started_at)}
                                {openIncidents[0].cause && ` · ${openIncidents[0].cause}`}
                            </p>
                        </div>
                        <ArrowRight className="size-4" />
                    </Link>
                )}

                {/* KPIs */}
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Kpi
                        label="Usuarios conectados"
                        value={<AnimatedNumber value={kpis.active_users} />}
                        hint="Actividad en los últimos 15 min"
                        icon={UsersRound}
                        href="/sessions"
                    />
                    <Kpi
                        label="Disponibilidad 24 h"
                        value={<AnimatedNumber value={kpis.uptime_avg} decimals={2} suffix="%" />}
                        hint="Promedio de todos los sistemas"
                        icon={Gauge}
                    />
                    <Kpi
                        label="Errores hoy"
                        value={<AnimatedNumber value={kpis.errors_today} />}
                        hint={`${kpis.open_error_groups} grupos sin resolver`}
                        icon={Bug}
                        tone={kpis.errors_today > 0 ? 'var(--status-serious)' : undefined}
                        href="/errors"
                    />
                    {costs ? (
                        <Kpi
                            label="Railway este mes"
                            value={usd.format(costs.total_projected)}
                            hint={`Proyección · consumido ${usd.format(costs.total_to_date)}`}
                            icon={CircleDollarSign}
                            href="/costs"
                        />
                    ) : (
                        <Kpi label="Alertas sin atender" value={<AnimatedNumber value={kpis.open_alerts} />} icon={Bell} href="/alerts" />
                    )}
                </div>

                <div className="grid gap-5 xl:grid-cols-[1.6fr_1fr]">
                    {/* Sistemas */}
                    <Panel
                        title="Sistemas"
                        description="Puntaje de salud: disponibilidad, errores, respuesta, seguridad y operación"
                        actions={
                            <Link href="/applications" className="text-muted-foreground hover:text-foreground text-xs">
                                Ver todos
                            </Link>
                        }
                    >
                        {applications.length === 0 ? (
                            <EmptyState icon={Activity} title="No hay aplicaciones registradas" />
                        ) : (
                            <ul className="-mx-2 grid gap-1">
                                {applications.map((app) => (
                                    <li key={app.id}>
                                        <Link
                                            href={`/applications/${app.id}`}
                                            className="hover:bg-accent/60 flex items-center gap-4 rounded-xl px-2 py-2.5 transition-colors"
                                        >
                                            <HealthRing score={app.score} size={44} stroke={4.5} />
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-center gap-2">
                                                    <span className="truncate font-medium">{app.name}</span>
                                                    <span
                                                        className="size-2 shrink-0 rounded-full"
                                                        style={{ background: statusColor(app.status) }}
                                                        title={statusLabel(app.status)}
                                                    />
                                                </div>
                                                <div className="text-muted-foreground flex flex-wrap gap-x-3 text-xs">
                                                    <span>{statusLabel(app.status)}</span>
                                                    {app.maintenance && (
                                                        <span className="inline-flex items-center gap-1 text-[var(--status-warning)]">
                                                            <Construction className="size-3" /> mantenimiento
                                                        </span>
                                                    )}
                                                    {app.findings > 0 && (
                                                        <span className="text-[var(--status-critical)]">{app.findings} hallazgos</span>
                                                    )}
                                                    {!!app.failed_jobs && (
                                                        <span className="inline-flex items-center gap-1">
                                                            <ListRestart className="size-3" /> {app.failed_jobs} fallidos
                                                        </span>
                                                    )}
                                                    {app.storage_percent !== null && app.storage_percent > 85 && (
                                                        <span className="text-[var(--status-critical)]">Disco {app.storage_percent}%</span>
                                                    )}
                                                    {app.type === 'laravel' && !app.agent && <span>sin agente</span>}
                                                </div>
                                            </div>
                                            <div className="hidden gap-6 text-right text-xs sm:flex">
                                                <Metric label="Respuesta" value={formatMs(app.last_response_ms)} />
                                                <Metric label="Uptime" value={formatPercent(app.uptime_24h, 1)} />
                                                <Metric label="Conectados" value={app.type === 'static' ? '—' : formatNumber(app.active_users)} />
                                                <Metric
                                                    label="Errores"
                                                    value={app.type === 'static' ? '—' : formatNumber(app.errors_today)}
                                                    warn={app.errors_today > 0}
                                                />
                                            </div>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>

                    {/* Actividad reciente */}
                    <Panel
                        title="Actividad reciente"
                        actions={
                            <Link href="/activity" className="text-muted-foreground hover:text-foreground text-xs">
                                En vivo →
                            </Link>
                        }
                    >
                        {feed.length === 0 ? (
                            <EmptyState icon={Activity} title="Sin actividad reciente" />
                        ) : (
                            <ul className="-my-1 space-y-1">
                                {feed.map((item) => {
                                    const { icon: Icon, color } = feedIcon(item);

                                    return (
                                        <li key={item.id}>
                                            <Link href={item.href} className="hover:bg-accent/60 -mx-2 flex gap-3 rounded-lg px-2 py-2">
                                                <span
                                                    className="flex size-8 shrink-0 items-center justify-center rounded-lg"
                                                    style={{ background: `color-mix(in oklab, ${color} 14%, transparent)` }}
                                                >
                                                    <Icon className="size-3.5" style={{ color }} />
                                                </span>
                                                <div className="min-w-0 text-sm">
                                                    <p className="truncate">{item.title}</p>
                                                    <p className="text-muted-foreground truncate text-xs">
                                                        {item.app} · {formatRelative(item.at)}
                                                    </p>
                                                </div>
                                            </Link>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </Panel>
                </div>

                <div className="grid gap-5 lg:grid-cols-2">
                    <Panel title="Peticiones por hora" description="Todas las apps con agente · últimas 24 h">
                        <TimeChart
                            data={traffic}
                            kind="bar"
                            series={[{ key: 'requests', label: 'Peticiones', color: 'var(--series-1)' }]}
                            format={(v) => formatNumber(v)}
                        />
                    </Panel>
                    <Panel title="Tiempo de respuesta promedio" description="Medido dentro de cada app · últimas 24 h">
                        <TimeChart
                            data={traffic}
                            series={[{ key: 'avg_ms', label: 'Promedio', color: 'var(--series-1)' }]}
                            format={(v) => formatMs(v)}
                        />
                    </Panel>
                </div>

                <div className="grid gap-5 lg:grid-cols-2">
                    <Panel
                        title="Alertas recientes"
                        actions={
                            <Link href="/alerts" className="text-muted-foreground hover:text-foreground text-xs">
                                {kpis.open_alerts > 0 ? `${kpis.open_alerts} sin atender` : 'Ver todas'}
                            </Link>
                        }
                    >
                        {recentAlerts.length === 0 ? (
                            <EmptyState icon={CheckCircle2} title="Sin alertas" />
                        ) : (
                            <ul className="space-y-3">
                                {recentAlerts.map((alert) => (
                                    <li key={alert.id} className="flex gap-2.5">
                                        <SeverityIcon severity={alert.severity} className="mt-0.5" />
                                        <div className="min-w-0">
                                            <p className={cn('text-sm', alert.acknowledged_at ? 'text-muted-foreground' : 'font-medium')}>
                                                {alert.title}
                                            </p>
                                            <p className="text-muted-foreground text-xs">{formatRelative(alert.created_at)}</p>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>

                    <Panel
                        title={
                            <span className="inline-flex items-center gap-2">
                                <ShieldAlert
                                    className="size-4"
                                    style={{ color: findings.length ? 'var(--status-critical)' : 'var(--status-good)' }}
                                />{' '}
                                Seguridad
                            </span>
                        }
                        description="Configuraciones riesgosas reportadas por el agente de cada app"
                    >
                        {findings.length === 0 ? (
                            <EmptyState icon={CheckCircle2} title="Sin hallazgos de seguridad" />
                        ) : (
                            <ul className="-my-1 divide-y text-sm">
                                {findings.map((f) => (
                                    <li key={`${f.app_id}-${f.key}`}>
                                        <Link
                                            href={`/applications/${f.app_id}#operaciones`}
                                            className="hover:bg-accent/40 -mx-2 flex justify-between gap-3 rounded px-2 py-2"
                                        >
                                            <span>
                                                <span className="font-medium">{f.label}</span>
                                                <span className="text-muted-foreground"> · {f.detail}</span>
                                            </span>
                                            <span className="text-muted-foreground shrink-0 text-xs">{f.app}</span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>
                </div>

                {kpis.failed_logins_today > 0 && (
                    <Link href="/logins?event=failed" className="bg-card hover:bg-accent/50 flex items-center gap-2 rounded-xl border p-3 text-sm">
                        <KeyRound className="size-4" style={{ color: 'var(--status-serious)' }} />
                        {kpis.failed_logins_today} intentos de inicio de sesión fallidos hoy en tus aplicaciones
                    </Link>
                )}
            </div>
        </AppLayout>
    );
}

function Kpi({
    label,
    value,
    hint,
    icon: Icon,
    tone,
    href,
}: {
    label: string;
    value: React.ReactNode;
    hint?: string;
    icon: typeof Bug;
    tone?: string;
    href?: string;
}) {
    const body = (
        <SpotlightCard className="h-full p-4" spotlightColor="rgba(91, 127, 255, 0.14)">
            <div className="text-muted-foreground flex items-center justify-between text-xs font-medium">
                <span>{label}</span>
                <span
                    className="bg-primary/10 text-primary flex size-8 items-center justify-center rounded-lg"
                    style={tone ? { color: tone, background: `color-mix(in oklab, ${tone} 14%, transparent)` } : undefined}
                >
                    <Icon className="size-4" />
                </span>
            </div>
            <div className="mt-2 text-3xl font-semibold tracking-tight">{value}</div>
            {hint && <div className="text-muted-foreground mt-1 text-xs">{hint}</div>}
        </SpotlightCard>
    );

    return href ? (
        <Link href={href} prefetch className="block">
            {body}
        </Link>
    ) : (
        body
    );
}

function Metric({ label, value, warn }: { label: string; value: string; warn?: boolean }) {
    return (
        <div className="w-[4.5rem] whitespace-nowrap">
            <div className={cn('text-foreground text-sm font-medium tabular-nums', warn && 'text-[var(--status-serious)]')}>{value}</div>
            <div className="text-muted-foreground">{label}</div>
        </div>
    );
}
