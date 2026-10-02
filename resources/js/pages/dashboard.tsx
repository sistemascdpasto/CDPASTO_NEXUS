import { TimeChart } from '@/components/charts';
import { EmptyState, PageHeader, Panel, StatCard } from '@/components/nexus-ui';
import { SeverityIcon, StatusDot, statusLabel } from '@/components/status-badge';
import AppLayout from '@/layouts/app-layout';
import { formatMs, formatNumber, formatPercent, formatRelative } from '@/lib/format';
import { type Alert, type AppStatus } from '@/types';
import { Head, Link, usePoll } from '@inertiajs/react';
import { Activity, Bell, Bug, CheckCircle2, Gauge, KeyRound, ShieldAlert, UsersRound } from 'lucide-react';

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
}

export default function Dashboard({ kpis, applications, traffic, recentAlerts, findings }: Props) {
    usePoll(30_000, {}, { keepAlive: false });

    const attention = kpis.apps_down + kpis.apps_degraded;

    return (
        <AppLayout breadcrumbs={[{ title: 'Resumen', href: '/dashboard' }]}>
            <Head title="Resumen" />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title="Resumen general"
                    description={
                        attention === 0 && kpis.apps_total > 0 ? (
                            <span className="inline-flex items-center gap-1.5">
                                <CheckCircle2 className="size-4" style={{ color: 'var(--status-good)' }} /> Todos los sistemas operan con normalidad
                            </span>
                        ) : (
                            `${attention} de ${kpis.apps_total} aplicaciones requieren atención`
                        )
                    }
                />

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <StatCard
                        label="Apps en línea"
                        value={`${kpis.apps_online} / ${kpis.apps_total}`}
                        hint={attention > 0 ? `${kpis.apps_down} caídas · ${kpis.apps_degraded} degradadas` : 'Sin incidentes'}
                        icon={Activity}
                        tone={kpis.apps_down > 0 ? 'var(--status-critical)' : kpis.apps_degraded > 0 ? 'var(--status-warning)' : 'var(--status-good)'}
                        href="/applications"
                    />
                    <StatCard
                        label="Usuarios activos"
                        value={formatNumber(kpis.active_users)}
                        hint="Actividad en los últimos 15 min"
                        icon={UsersRound}
                        href="/sessions"
                    />
                    <StatCard
                        label="Errores hoy"
                        value={formatNumber(kpis.errors_today)}
                        hint={`${kpis.open_error_groups} grupos sin resolver`}
                        icon={Bug}
                        tone={kpis.errors_today > 0 ? 'var(--status-serious)' : undefined}
                        href="/errors"
                    />
                    <StatCard label="Uptime promedio (24 h)" value={formatPercent(kpis.uptime_avg)} hint="Todas las apps activas" icon={Gauge} />
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Panel
                        title="Estado de las aplicaciones"
                        className="lg:col-span-2"
                        actions={
                            <Link href="/applications" className="text-muted-foreground text-xs hover:underline">
                                Ver todas
                            </Link>
                        }
                    >
                        {applications.length === 0 ? (
                            <EmptyState icon={Activity} title="No hay aplicaciones registradas" />
                        ) : (
                            <ul className="-my-2 divide-y">
                                {applications.map((app) => (
                                    <li key={app.id}>
                                        <Link
                                            href={`/applications/${app.id}`}
                                            className="hover:bg-accent/50 -mx-2 flex items-center gap-3 rounded-md px-2 py-2.5"
                                        >
                                            <StatusDot status={app.status} />
                                            <div className="min-w-0 flex-1">
                                                <div className="truncate text-sm font-medium">{app.name}</div>
                                                <div className="text-muted-foreground text-xs">
                                                    {statusLabel(app.status)} · revisada {formatRelative(app.last_checked_at)}
                                                    {app.maintenance && <Flag color="var(--status-warning)">En mantenimiento</Flag>}
                                                    {app.findings > 0 && (
                                                        <Flag color="var(--status-critical)">{app.findings} hallazgos de seguridad</Flag>
                                                    )}
                                                    {!!app.failed_jobs && (
                                                        <Flag color="var(--status-serious)">{app.failed_jobs} trabajos fallidos</Flag>
                                                    )}
                                                    {app.storage_percent !== null && app.storage_percent > 85 && (
                                                        <Flag color="var(--status-critical)">Disco al {app.storage_percent}%</Flag>
                                                    )}
                                                    {app.type === 'laravel' && !app.agent && <span className="ml-2">· sin agente</span>}
                                                </div>
                                            </div>
                                            <div className="text-muted-foreground hidden gap-5 text-right text-xs sm:flex">
                                                <Metric label="Respuesta" value={formatMs(app.last_response_ms)} />
                                                <Metric label="Uptime 24h" value={formatPercent(app.uptime_24h, 1)} />
                                                <Metric label="Usuarios" value={app.type === 'static' ? '—' : String(app.active_users)} />
                                                <Metric label="Errores hoy" value={app.type === 'static' ? '—' : String(app.errors_today)} />
                                            </div>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>

                    <Panel
                        title="Alertas recientes"
                        actions={
                            <Link href="/alerts" className="text-muted-foreground text-xs hover:underline">
                                {kpis.open_alerts > 0 ? `${kpis.open_alerts} sin atender` : 'Ver todas'}
                            </Link>
                        }
                    >
                        {recentAlerts.length === 0 ? (
                            <EmptyState icon={Bell} title="Sin alertas" />
                        ) : (
                            <ul className="space-y-3">
                                {recentAlerts.map((alert) => (
                                    <li key={alert.id} className="flex gap-2.5">
                                        <SeverityIcon severity={alert.severity} className="mt-0.5" />
                                        <div className="min-w-0">
                                            <p className={`text-sm ${alert.acknowledged_at ? 'text-muted-foreground' : 'font-medium'}`}>
                                                {alert.title}
                                            </p>
                                            <p className="text-muted-foreground text-xs">{formatRelative(alert.created_at)}</p>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Panel title="Peticiones por hora" description="Todas las apps con agente · últimas 24 h">
                        <TimeChart
                            data={traffic}
                            kind="bar"
                            series={[{ key: 'requests', label: 'Peticiones', color: 'var(--series-1)' }]}
                            format={(v) => formatNumber(v)}
                        />
                    </Panel>
                    <Panel title="Tiempo de respuesta promedio" description="Promedio ponderado de todas las rutas · últimas 24 h">
                        <TimeChart
                            data={traffic}
                            series={[{ key: 'avg_ms', label: 'Promedio', color: 'var(--series-1)' }]}
                            format={(v) => formatMs(v)}
                        />
                    </Panel>
                </div>

                {findings.length > 0 && (
                    <Panel
                        title={
                            <span className="inline-flex items-center gap-2">
                                <ShieldAlert className="size-4" style={{ color: 'var(--status-critical)' }} /> Hallazgos de seguridad
                            </span>
                        }
                        description="Configuraciones riesgosas reportadas por el agente de cada app"
                    >
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
                    </Panel>
                )}

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

function Flag({ color, children }: { color: string; children: React.ReactNode }) {
    return (
        <span className="ml-2 inline-flex items-center gap-1">
            · <span className="size-1.5 rounded-full" style={{ background: color }} />
            {children}
        </span>
    );
}

function Metric({ label, value }: { label: string; value: string }) {
    return (
        <div className="w-16">
            <div className="text-foreground text-sm font-medium tabular-nums">{value}</div>
            <div>{label}</div>
        </div>
    );
}
