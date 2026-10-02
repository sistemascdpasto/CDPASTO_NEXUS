import { DeploymentLogsDialog, OperationsTab, type AgentInfoData, type SecurityCheck } from '@/components/app-operations';
import { TimeChart, UptimeStrip } from '@/components/charts';
import { EmptyState, PageHeader, Panel, StatCard, Table } from '@/components/nexus-ui';
import { ActionBadge, LoginEventBadge, SeverityIcon, StatusBadge, statusColor } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { UnblockButton, UserActionsMenu } from '@/components/user-action-dialog';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime, formatMs, formatNumber, formatPercent, formatRelative, formatTime, shortHash } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type Alert, type AppSession, type AppStatus, type AuditLog, type Deployment, type LoginEvent } from '@/types';
import { Head, Link, router, usePoll } from '@inertiajs/react';
import {
    Bug,
    CheckCircle2,
    Copy,
    Cpu,
    Database,
    ExternalLink,
    GitCommit,
    HardDrive,
    KeyRound,
    Layers,
    MoreVertical,
    Network,
    RefreshCw,
    Rocket,
    Settings,
    Timer,
    Users,
    XCircle,
} from 'lucide-react';
import { useEffect, useState } from 'react';

interface Component {
    ok: boolean;
    ms?: number;
    error?: string;
    driver?: string;
    pending?: number;
    failed?: number;
    oldest_pending_minutes?: number | null;
}

interface Props {
    application: {
        id: number;
        name: string;
        type: 'laravel' | 'static';
        url: string;
        environment: string;
        is_active: boolean;
        status: AppStatus;
        last_status_code: number | null;
        last_response_ms: number | null;
        last_checked_at: string | null;
        status_changed_at: string | null;
        railway_service_id: string | null;
        railway_service_name: string | null;
        description: string | null;
        repository: string | null;
        health_url: string;
        check_interval_minutes: number;
        slow_threshold_ms: number;
        error_threshold: number;
        failed_login_threshold: number;
        mass_delete_threshold: number;
        api_key_prefix: string;
        last_ingest_at: string | null;
        agent_version: string | null;
        components: Record<string, Component> | null;
        database_service_name: string | null;
        has_database: boolean;
        info: AgentInfoData | null;
        info_at: string | null;
        findings: SecurityCheck[];
    };
    range: '24h' | '7d' | '30d';
    can: { manage: boolean; operate: boolean };
    apiKey: string | null;
    uptime: { day: number | null; week: number | null; month: number | null; daily: { date: string; uptime: number; checks: number }[] };
    healthSeries: { time: string; avg_ms: number; max_ms: number }[];
    recentChecks: {
        id: number;
        status: AppStatus;
        http_status: number | null;
        response_ms: number | null;
        error: string | null;
        checked_at: string;
    }[];
    requests: {
        totals: { requests: number; avg_ms: number; errors_4xx: number; errors_5xx: number };
        series: { time: string; requests: number; avg_ms: number; errors: number }[];
        slowest: { method: string; route: string; requests: number; avg_ms: number; max_ms: number; errors: number }[];
    };
    resources: { time: string; cpu: number | null; memory_gb: number | null; network_rx_gb: number | null; network_tx_gb: number | null }[];
    deployments: Deployment[];
    errors: {
        id: number;
        exception_class: string;
        message: string;
        file: string | null;
        line: number | null;
        occurrences: number;
        last_seen_at: string;
    }[];
    sessions: AppSession[];
    blocks: { external_user_id: string; target_name: string | null; blocked_at: string; minutes: number | null; reason: string | null }[];
    logins: LoginEvent[];
    audits: AuditLog[];
    alerts: Alert[];
}

const tabs = [
    { id: 'resumen', label: 'Resumen' },
    { id: 'rendimiento', label: 'Rendimiento' },
    { id: 'usuarios', label: 'Usuarios' },
    { id: 'auditoria', label: 'Auditoría' },
    { id: 'despliegues', label: 'Despliegues' },
    { id: 'recursos', label: 'Recursos' },
    { id: 'operaciones', label: 'Operaciones' },
    { id: 'integracion', label: 'Integración' },
] as const;

type TabId = (typeof tabs)[number]['id'];

const ranges = [
    { id: '24h', label: '24 h' },
    { id: '7d', label: '7 días' },
    { id: '30d', label: '30 días' },
];

export default function ApplicationShow(props: Props) {
    const { application: app, range, can, apiKey } = props;
    const [tab, setTab] = useState<TabId>(() => {
        const hash = typeof window !== 'undefined' ? window.location.hash.slice(1) : '';

        return (tabs.find((t) => t.id === hash)?.id ?? (apiKey ? 'integracion' : 'resumen')) as TabId;
    });

    useEffect(() => {
        window.history.replaceState(window.history.state, '', `${window.location.pathname}${window.location.search}#${tab}`);
    }, [tab]);

    usePoll(60_000, {}, { keepAlive: false });

    const isLaravel = app.type === 'laravel';
    const visibleTabs = tabs
        .filter((t) => isLaravel || !['usuarios', 'auditoria', 'rendimiento'].includes(t.id))
        .filter((t) => !['integracion', 'operaciones'].includes(t.id) || can.manage);

    const setRange = (value: string) =>
        router.get(
            `/applications/${app.id}`,
            { range: value },
            { preserveState: true, preserveScroll: true, only: ['range', 'healthSeries', 'requests', 'resources'] },
        );

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Aplicaciones', href: '/applications' },
                { title: app.name, href: `/applications/${app.id}` },
            ]}
        >
            <Head title={app.name} />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title={app.name}
                    description={
                        <span className="flex flex-wrap items-center gap-x-3 gap-y-1">
                            {app.is_active ? <StatusBadge status={app.status} /> : <span>Desactivada</span>}
                            <a href={app.url} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 hover:underline">
                                {app.url.replace(/^https?:\/\//, '')} <ExternalLink className="size-3" />
                            </a>
                            <span>{app.environment}</span>
                            {app.status_changed_at && <span>Estado desde {formatRelative(app.status_changed_at)}</span>}
                        </span>
                    }
                    actions={
                        <>
                            {isLaravel && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={`/applications/${app.id}/users`}>
                                        <Users /> Usuarios
                                    </Link>
                                </Button>
                            )}
                            {can.manage && app.has_database && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={`/applications/${app.id}/database`}>
                                        <Database /> Base de datos
                                    </Link>
                                </Button>
                            )}
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => router.post(`/applications/${app.id}/check`, {}, { preserveScroll: true })}
                            >
                                <RefreshCw /> Verificar ahora
                            </Button>
                            {app.railway_service_id && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => router.post(`/applications/${app.id}/sync-railway`, {}, { preserveScroll: true })}
                                >
                                    <Rocket /> Actualizar Railway
                                </Button>
                            )}
                            {can.manage && (
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button variant="outline" size="icon" aria-label="Más opciones">
                                            <MoreVertical />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem asChild>
                                            <Link href={`/applications/${app.id}/edit`}>
                                                <Settings /> Editar configuración
                                            </Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            onSelect={() => router.post(`/applications/${app.id}/toggle`, {}, { preserveScroll: true })}
                                        >
                                            {app.is_active ? <XCircle /> : <CheckCircle2 />}
                                            {app.is_active ? 'Desactivar monitoreo' : 'Activar monitoreo'}
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            className="text-destructive"
                                            onSelect={() => {
                                                if (confirm(`¿Eliminar ${app.name} y toda su telemetría? Esta acción no se puede deshacer.`)) {
                                                    router.delete(`/applications/${app.id}`);
                                                }
                                            }}
                                        >
                                            Eliminar aplicación
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            )}
                        </>
                    }
                />

                <nav className="-mx-4 flex gap-1 overflow-x-auto border-b px-4" role="tablist">
                    {visibleTabs.map((t) => (
                        <button
                            key={t.id}
                            role="tab"
                            aria-selected={tab === t.id}
                            onClick={() => setTab(t.id)}
                            className={cn(
                                '-mb-px border-b-2 px-3 py-2 text-sm whitespace-nowrap',
                                tab === t.id ? 'border-foreground font-medium' : 'text-muted-foreground hover:text-foreground border-transparent',
                            )}
                        >
                            {t.label}
                            {t.id === 'usuarios' && props.sessions.length > 0 && (
                                <span className="text-muted-foreground ml-1.5 text-xs">{props.sessions.length}</span>
                            )}
                        </button>
                    ))}
                </nav>

                {['rendimiento', 'recursos'].includes(tab) && (
                    <div className="flex gap-1.5">
                        {ranges.map((r) => (
                            <button
                                key={r.id}
                                onClick={() => setRange(r.id)}
                                className={cn(
                                    'rounded-full border px-3 py-1 text-xs',
                                    range === r.id ? 'bg-primary text-primary-foreground border-primary' : 'hover:bg-accent',
                                )}
                            >
                                {r.label}
                            </button>
                        ))}
                    </div>
                )}

                {tab === 'resumen' && <OverviewTab {...props} />}
                {tab === 'rendimiento' && <PerformanceTab {...props} />}
                {tab === 'usuarios' && <UsersTab {...props} />}
                {tab === 'auditoria' && <AuditTab {...props} />}
                {tab === 'despliegues' && <DeploymentsTab {...props} />}
                {tab === 'recursos' && <ResourcesTab {...props} />}
                {tab === 'operaciones' && <OperationsTab {...props} />}
                {tab === 'integracion' && <IntegrationTab {...props} />}
            </div>
        </AppLayout>
    );
}

function OverviewTab({ application: app, uptime, recentChecks, errors, alerts, sessions, deployments }: Props) {
    const lastDeploy = deployments[0];

    return (
        <div className="flex flex-col gap-4">
            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <StatCard
                    label="Uptime 24 h"
                    value={formatPercent(uptime.day)}
                    hint={`7 d: ${formatPercent(uptime.week)} · 30 d: ${formatPercent(uptime.month)}`}
                />
                <StatCard
                    label="Última respuesta"
                    value={formatMs(app.last_response_ms)}
                    hint={`HTTP ${app.last_status_code ?? '—'} · cada ${app.check_interval_minutes} min`}
                    icon={Timer}
                />
                <StatCard label="Usuarios activos" value={app.type === 'static' ? '—' : sessions.length} hint="Últimos 15 minutos" icon={Users} />
                <StatCard
                    label="Último despliegue"
                    value={lastDeploy ? formatRelative(lastDeploy.deployed_at) : '—'}
                    hint={lastDeploy ? <DeployStatus status={lastDeploy.status} /> : 'Sin datos de Railway'}
                    icon={Rocket}
                />
            </div>

            <Panel title="Disponibilidad diaria" description="Últimos 30 días · porcentaje de verificaciones exitosas">
                <UptimeStrip daily={uptime.daily} />
            </Panel>

            <div className="grid gap-4 lg:grid-cols-2">
                <Panel title="Componentes internos" description="Base de datos, caché y cola reportados por el agente">
                    {app.components ? (
                        <div className="grid gap-2 sm:grid-cols-2">
                            {Object.entries(app.components).map(([name, c]) => (
                                <ComponentCard key={name} name={name} component={c} />
                            ))}
                        </div>
                    ) : (
                        <EmptyState icon={Database} title="Sin datos de componentes">
                            {app.type === 'static'
                                ? 'Las apps estáticas solo se monitorean por HTTP.'
                                : 'Instala el agente Nexus en la app para ver el estado de MySQL, caché y cola.'}
                        </EmptyState>
                    )}
                </Panel>

                <Panel title="Últimas verificaciones">
                    <ul className="-my-1 divide-y text-sm">
                        {recentChecks.map((c) => (
                            <li key={c.id} className="flex items-center gap-3 py-1.5">
                                <span className="size-2 shrink-0 rounded-full" style={{ background: statusColor(c.status) }} />
                                <span className="text-muted-foreground w-12 text-xs tabular-nums">{formatTime(c.checked_at)}</span>
                                <span className="w-14 text-xs">HTTP {c.http_status ?? '—'}</span>
                                <span className="w-16 text-xs tabular-nums">{formatMs(c.response_ms)}</span>
                                <span className="text-muted-foreground truncate text-xs" title={c.error ?? undefined}>
                                    {c.error}
                                </span>
                            </li>
                        ))}
                        {recentChecks.length === 0 && <li className="text-muted-foreground py-4 text-center text-xs">Aún no hay verificaciones</li>}
                    </ul>
                </Panel>
            </div>

            {app.type === 'laravel' && (
                <div className="grid gap-4 lg:grid-cols-2">
                    <Panel
                        title="Errores sin resolver"
                        actions={
                            <Link href={`/errors?application_id=${app.id}`} className="text-muted-foreground text-xs hover:underline">
                                Ver todos
                            </Link>
                        }
                    >
                        {errors.length === 0 ? (
                            <EmptyState icon={Bug} title="Sin errores abiertos" />
                        ) : (
                            <ul className="-my-1 divide-y">
                                {errors.map((e) => (
                                    <li key={e.id}>
                                        <Link href={`/errors/${e.id}`} className="hover:bg-accent/50 -mx-2 block rounded-md px-2 py-2">
                                            <div className="flex justify-between gap-2 text-sm">
                                                <span className="truncate font-medium">{e.exception_class.split('\\').pop()}</span>
                                                <span className="text-muted-foreground shrink-0 text-xs">
                                                    {formatNumber(e.occurrences)}× · {formatRelative(e.last_seen_at)}
                                                </span>
                                            </div>
                                            <p className="text-muted-foreground truncate text-xs">{e.message}</p>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>
                    <AlertsPanel alerts={alerts} />
                </div>
            )}
            {app.type === 'static' && <AlertsPanel alerts={alerts} />}
        </div>
    );
}

function AlertsPanel({ alerts }: { alerts: Alert[] }) {
    return (
        <Panel title="Alertas de esta app">
            {alerts.length === 0 ? (
                <EmptyState title="Sin alertas" />
            ) : (
                <ul className="space-y-2.5">
                    {alerts.map((a) => (
                        <li key={a.id} className="flex gap-2">
                            <SeverityIcon severity={a.severity} className="mt-0.5" />
                            <div className="min-w-0">
                                <p className="text-sm">{a.title}</p>
                                <p className="text-muted-foreground truncate text-xs">
                                    {formatDateTime(a.created_at)} · {a.message}
                                </p>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </Panel>
    );
}

const componentLabels: Record<string, { label: string; Icon: typeof Database }> = {
    database: { label: 'Base de datos', Icon: Database },
    cache: { label: 'Caché', Icon: HardDrive },
    queue: { label: 'Cola de trabajos', Icon: Layers },
    storage: { label: 'Almacenamiento', Icon: HardDrive },
};

function ComponentCard({ name, component: c }: { name: string; component: Component }) {
    const meta = componentLabels[name] ?? { label: name, Icon: Layers };

    return (
        <div className="flex gap-3 rounded-lg border p-3">
            <meta.Icon className="text-muted-foreground mt-0.5 size-4" />
            <div className="min-w-0 flex-1 text-sm">
                <div className="flex items-center justify-between gap-2">
                    <span className="font-medium">{meta.label}</span>
                    <span className="inline-flex items-center gap-1 text-xs">
                        {c.ok ? (
                            <CheckCircle2 className="size-3.5" style={{ color: 'var(--status-good)' }} />
                        ) : (
                            <XCircle className="size-3.5" style={{ color: 'var(--status-critical)' }} />
                        )}
                        {c.ok ? 'OK' : 'Falla'}
                    </span>
                </div>
                <p className="text-muted-foreground text-xs">
                    {[
                        c.driver,
                        c.ms !== undefined ? formatMs(c.ms) : null,
                        c.pending !== undefined ? `${c.pending} en cola` : null,
                        c.failed !== undefined ? `${c.failed} fallidos` : null,
                    ]
                        .filter(Boolean)
                        .join(' · ')}
                </p>
                {c.oldest_pending_minutes ? (
                    <p className="text-xs" style={{ color: 'var(--status-serious)' }}>
                        Trabajo más antiguo esperando {c.oldest_pending_minutes} min
                    </p>
                ) : null}
                {c.error && (
                    <p className="text-destructive truncate text-xs" title={c.error}>
                        {c.error}
                    </p>
                )}
            </div>
        </div>
    );
}

function PerformanceTab({ requests, healthSeries, range }: Props) {
    const t = requests.totals;
    const errorRate = t.requests ? ((t.errors_5xx / t.requests) * 100).toFixed(2) : null;

    return (
        <div className="flex flex-col gap-4">
            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <StatCard label="Peticiones" value={formatNumber(t.requests)} />
                <StatCard label="Tiempo promedio" value={formatMs(t.avg_ms)} icon={Timer} />
                <StatCard
                    label="Errores 5xx"
                    value={formatNumber(t.errors_5xx)}
                    hint={errorRate !== null ? `${errorRate}% de las peticiones` : undefined}
                />
                <StatCard label="Respuestas 4xx" value={formatNumber(t.errors_4xx)} hint="No encontrado, sin permiso, validación" />
            </div>
            <div className="grid gap-4 lg:grid-cols-2">
                <Panel title={`Peticiones por ${range === '24h' ? 'hora' : 'día'}`}>
                    <TimeChart
                        data={requests.series}
                        kind="bar"
                        series={[{ key: 'requests', label: 'Peticiones', color: 'var(--series-1)' }]}
                        format={(v) => formatNumber(v)}
                    />
                </Panel>
                <Panel title="Tiempo de respuesta promedio" description="Medido por el agente dentro de la app">
                    <TimeChart
                        data={requests.series}
                        series={[{ key: 'avg_ms', label: 'Promedio', color: 'var(--series-1)' }]}
                        format={(v) => formatMs(v)}
                    />
                </Panel>
            </div>
            <Panel title="Rutas más lentas" description="Ordenadas por tiempo promedio de respuesta">
                {requests.slowest.length === 0 ? (
                    <EmptyState title="Sin datos de peticiones">El agente envía las métricas de peticiones cada minuto.</EmptyState>
                ) : (
                    <Table>
                        <thead>
                            <tr>
                                <th>Ruta</th>
                                <th className="text-right">Peticiones</th>
                                <th className="text-right">Promedio</th>
                                <th className="text-right">Máximo</th>
                                <th className="text-right">Errores 5xx</th>
                            </tr>
                        </thead>
                        <tbody>
                            {requests.slowest.map((r) => (
                                <tr key={`${r.method} ${r.route}`}>
                                    <td className="font-mono text-xs">
                                        <span className="text-muted-foreground mr-2">{r.method}</span>
                                        {r.route}
                                    </td>
                                    <td className="text-right tabular-nums">{formatNumber(r.requests)}</td>
                                    <td className="text-right font-medium tabular-nums">{formatMs(r.avg_ms)}</td>
                                    <td className="text-right tabular-nums">{formatMs(r.max_ms)}</td>
                                    <td className="text-right tabular-nums">{r.errors || '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </Table>
                )}
            </Panel>
            <Panel title="Tiempo de respuesta externo" description="Medido por Nexus desde fuera (health check), incluye red">
                <TimeChart
                    data={healthSeries}
                    series={[{ key: 'avg_ms', label: 'Promedio', color: 'var(--series-1)' }]}
                    format={(v) => formatMs(v)}
                />
            </Panel>
        </div>
    );
}

function UsersTab({ application: app, sessions, blocks, logins, can }: Props) {
    return (
        <div className="flex flex-col gap-4">
            <Panel title="Conectados ahora" description="Usuarios con actividad en los últimos 15 minutos">
                {sessions.length === 0 ? (
                    <EmptyState icon={Users} title="Nadie conectado en este momento" />
                ) : (
                    <Table>
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Rol</th>
                                <th>Inicio de sesión</th>
                                <th>Última actividad</th>
                                <th>Dispositivo / IP</th>
                                {can.operate && <th />}
                            </tr>
                        </thead>
                        <tbody>
                            {sessions.map((s) => (
                                <tr key={s.id}>
                                    <td>
                                        <div className="font-medium">{s.user_name ?? `#${s.external_user_id}`}</div>
                                        <div className="text-muted-foreground text-xs">{s.user_email}</div>
                                    </td>
                                    <td className="text-xs">{s.user_role ?? '—'}</td>
                                    <td className="text-xs">{formatDateTime(s.login_at)}</td>
                                    <td className="text-xs">{formatRelative(s.last_activity_at)}</td>
                                    <td className="text-muted-foreground text-xs">
                                        {s.device}
                                        <br />
                                        {s.ip}
                                    </td>
                                    {can.operate && (
                                        <td className="text-right">
                                            <UserActionsMenu
                                                target={{ applicationId: app.id, externalUserId: s.external_user_id, name: s.user_name }}
                                            />
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </Table>
                )}
            </Panel>

            {blocks.length > 0 && (
                <Panel title="Usuarios bloqueados">
                    <ul className="divide-y">
                        {blocks.map((b) => (
                            <li key={b.external_user_id} className="flex items-center justify-between gap-2 py-2 text-sm">
                                <div>
                                    <div className="font-medium">{b.target_name ?? `#${b.external_user_id}`}</div>
                                    <div className="text-muted-foreground text-xs">
                                        Desde {formatDateTime(b.blocked_at)} · {b.minutes ? `${b.minutes} min` : 'indefinido'}
                                        {b.reason && ` · ${b.reason}`}
                                    </div>
                                </div>
                                {can.operate && (
                                    <UnblockButton target={{ applicationId: app.id, externalUserId: b.external_user_id, name: b.target_name }} />
                                )}
                            </li>
                        ))}
                    </ul>
                </Panel>
            )}

            <Panel
                title="Últimos inicios de sesión"
                actions={
                    <Link href={`/logins?application_id=${app.id}`} className="text-muted-foreground text-xs hover:underline">
                        Historial completo
                    </Link>
                }
            >
                <LoginList logins={logins} />
            </Panel>
        </div>
    );
}

function LoginList({ logins }: { logins: LoginEvent[] }) {
    if (logins.length === 0) return <EmptyState icon={KeyRound} title="Sin inicios de sesión registrados" />;

    return (
        <Table>
            <tbody>
                {logins.map((l) => (
                    <tr key={l.id}>
                        <td className="w-36">
                            <LoginEventBadge event={l.event} />
                        </td>
                        <td>{l.user_name ?? l.identifier ?? '—'}</td>
                        <td className="text-muted-foreground text-xs">{l.ip}</td>
                        <td className="text-muted-foreground text-xs">{l.device}</td>
                        <td className="text-muted-foreground text-right text-xs">{formatDateTime(l.occurred_at)}</td>
                    </tr>
                ))}
            </tbody>
        </Table>
    );
}

function AuditTab({ application: app, audits }: Props) {
    return (
        <Panel
            title="Actividad reciente"
            actions={
                <Link href={`/audit?application_id=${app.id}`} className="text-muted-foreground text-xs hover:underline">
                    Auditoría completa y exportación
                </Link>
            }
        >
            {audits.length === 0 ? (
                <EmptyState title="Sin registros de auditoría" />
            ) : (
                <Table>
                    <tbody>
                        {audits.map((a) => (
                            <tr key={a.id}>
                                <td className="w-28">
                                    <ActionBadge action={a.action} />
                                </td>
                                <td>
                                    {a.module} {a.record_id && <span className="text-muted-foreground">#{a.record_id}</span>}
                                </td>
                                <td>{a.user_name ?? 'Sistema'}</td>
                                <td className="text-muted-foreground text-right text-xs">{formatDateTime(a.occurred_at)}</td>
                            </tr>
                        ))}
                    </tbody>
                </Table>
            )}
        </Panel>
    );
}

function DeployStatus({ status }: { status: string }) {
    const color: Record<string, string> = {
        SUCCESS: 'var(--status-good)',
        FAILED: 'var(--status-critical)',
        CRASHED: 'var(--status-critical)',
        BUILDING: 'var(--status-warning)',
        DEPLOYING: 'var(--status-warning)',
        REMOVED: 'var(--status-unknown)',
    };
    const labels: Record<string, string> = {
        SUCCESS: 'Exitoso',
        FAILED: 'Fallido',
        CRASHED: 'Se cayó',
        BUILDING: 'Construyendo',
        DEPLOYING: 'Desplegando',
        REMOVED: 'Reemplazado',
        SKIPPED: 'Omitido',
        INITIALIZING: 'Iniciando',
        QUEUED: 'En cola',
        WAITING: 'En espera',
        SLEEPING: 'Dormido',
    };

    return (
        <span className="inline-flex items-center gap-1.5 text-xs">
            <span className="size-2 rounded-full" style={{ background: color[status] ?? 'var(--status-unknown)' }} />
            {labels[status] ?? status}
        </span>
    );
}

function DeploymentsTab({ application: app, deployments, can }: Props) {
    const [logsFor, setLogsFor] = useState<Deployment | null>(null);

    if (!app.railway_service_id) {
        return (
            <Panel>
                <EmptyState icon={Rocket} title="Sin servicio de Railway vinculado">
                    Edita la aplicación y selecciona su servicio de Railway para ver despliegues y consumo.
                </EmptyState>
            </Panel>
        );
    }

    return (
        <Panel title="Despliegues en Railway" description={app.railway_service_name ?? undefined}>
            {deployments.length === 0 ? (
                <EmptyState icon={Rocket} title="Sin despliegues sincronizados" />
            ) : (
                <ol className="relative space-y-4 border-l pl-5">
                    {deployments.map((d) => (
                        <li key={d.id} className="relative">
                            <span className="bg-card absolute top-1 -left-[26px] rounded-full border p-0.5">
                                <GitCommit className="size-3" />
                            </span>
                            <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                                <DeployStatus status={d.status} />
                                <span className="text-sm font-medium">{d.commit_message ?? 'Despliegue manual'}</span>
                            </div>
                            <p className="text-muted-foreground mt-0.5 text-xs">
                                {formatDateTime(d.deployed_at)} · {d.commit_author ?? '—'} · {d.branch ?? '—'} ·{' '}
                                {app.repository && d.commit_hash ? (
                                    <a
                                        href={`https://github.com/${app.repository}/commit/${d.commit_hash}`}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="font-mono hover:underline"
                                    >
                                        {shortHash(d.commit_hash)}
                                    </a>
                                ) : (
                                    <span className="font-mono">{shortHash(d.commit_hash)}</span>
                                )}
                                {can.manage && (
                                    <>
                                        {' · '}
                                        <button className="hover:text-foreground underline underline-offset-2" onClick={() => setLogsFor(d)}>
                                            Ver logs
                                        </button>
                                    </>
                                )}
                            </p>
                        </li>
                    ))}
                </ol>
            )}
            {logsFor && <DeploymentLogsDialog application={app} deployment={logsFor} onClose={() => setLogsFor(null)} />}
        </Panel>
    );
}

function ResourcesTab({ application: app, resources }: Props) {
    if (!app.railway_service_id) {
        return (
            <Panel>
                <EmptyState icon={Cpu} title="Sin servicio de Railway vinculado">
                    Edita la aplicación y selecciona su servicio de Railway para ver CPU, memoria y red.
                </EmptyState>
            </Panel>
        );
    }

    const last = [...resources].reverse().find((r) => r.memory_gb !== null);
    const gb = (v: number) => (v >= 1 ? `${v.toFixed(2)} GB` : `${Math.round(v * 1024)} MB`);

    return (
        <div className="flex flex-col gap-4">
            <div className="grid grid-cols-2 gap-3 lg:grid-cols-3">
                <StatCard label="CPU actual" value={last?.cpu !== null && last?.cpu !== undefined ? `${last.cpu.toFixed(3)} vCPU` : '—'} icon={Cpu} />
                <StatCard label="Memoria actual" value={last?.memory_gb ? gb(last.memory_gb) : '—'} icon={HardDrive} />
                <StatCard
                    label="Red (periodo)"
                    value={gb(resources.reduce((s, r) => s + (r.network_tx_gb ?? 0), 0))}
                    hint="Datos enviados (egress)"
                    icon={Network}
                />
            </div>
            <div className="grid gap-4 lg:grid-cols-2">
                <Panel title="CPU" description="vCPU promedio">
                    <TimeChart
                        data={resources}
                        xFormat={(v) => formatTime(v)}
                        tooltipXFormat={(v) => formatDateTime(v.replace(' ', 'T'))}
                        series={[{ key: 'cpu', label: 'CPU', color: 'var(--series-1)' }]}
                        format={(v) => v.toFixed(3)}
                    />
                </Panel>
                <Panel title="Memoria" description="GB en uso">
                    <TimeChart
                        data={resources}
                        xFormat={(v) => formatTime(v)}
                        tooltipXFormat={(v) => formatDateTime(v.replace(' ', 'T'))}
                        series={[{ key: 'memory_gb', label: 'Memoria', color: 'var(--series-1)' }]}
                        format={(v) => gb(v)}
                    />
                </Panel>
            </div>
            <Panel title="Red" description="Datos recibidos y enviados por intervalo">
                <TimeChart
                    data={resources}
                    xFormat={(v) => formatTime(v)}
                    tooltipXFormat={(v) => formatDateTime(v.replace(' ', 'T'))}
                    series={[
                        { key: 'network_rx_gb', label: 'Recibido', color: 'var(--series-1)' },
                        { key: 'network_tx_gb', label: 'Enviado', color: 'var(--series-2)' },
                    ]}
                    format={(v) => gb(v)}
                />
            </Panel>
        </div>
    );
}

function IntegrationTab({ application: app, apiKey }: Props) {
    const nexusUrl = typeof window !== 'undefined' ? window.location.origin : '';

    return (
        <div className="flex flex-col gap-4">
            {apiKey && (
                <div className="rounded-xl border-2 border-dashed p-4" style={{ borderColor: 'var(--status-warning)' }}>
                    <p className="text-sm font-medium">API key generada — cópiala ahora, no se volverá a mostrar.</p>
                    <CopyField value={apiKey} />
                </div>
            )}

            <Panel title="Credenciales del agente">
                <dl className="grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt className="text-muted-foreground text-xs">API key</dt>
                        <dd className="font-mono">{app.api_key_prefix}••••••••••</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground text-xs">Último envío del agente</dt>
                        <dd>
                            {app.last_ingest_at ? formatRelative(app.last_ingest_at) : 'Nunca'}{' '}
                            {app.agent_version && <span className="text-muted-foreground">· v{app.agent_version}</span>}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground text-xs">Endpoint de salud</dt>
                        <dd className="font-mono text-xs break-all">{app.health_url}</dd>
                    </div>
                </dl>
                <Button
                    variant="outline"
                    size="sm"
                    className="mt-4"
                    onClick={() =>
                        confirm('La key actual dejará de funcionar inmediatamente. ¿Continuar?') &&
                        router.post(`/applications/${app.id}/regenerate-key`)
                    }
                >
                    <KeyRound /> Regenerar API key
                </Button>
            </Panel>

            {app.type === 'laravel' && (
                <Panel title="Instalar el agente en la app">
                    <ol className="list-decimal space-y-3 pl-5 text-sm">
                        <li>
                            El agente va dentro del repo de la app en <code className="bg-muted rounded px-1">packages/nexus-agent</code> (Adenar,
                            EasyOL y Tickets ya lo tienen). Para una app nueva, cópialo desde Nexus y requiérelo:
                            <CopyField
                                value={
                                    'composer config repositories.nexus-agent \'{"type":"path","url":"packages/nexus-agent","options":{"symlink":false}}\'\ncomposer require cdpasto/nexus-agent:^1.1'
                                }
                            />
                        </li>
                        <li>
                            Variables de entorno en el servicio <strong>web</strong> y en el <strong>scheduler</strong> de Railway:
                            <CopyField value={`NEXUS_URL=${nexusUrl}\nNEXUS_KEY=${apiKey ?? '<regenera la API key para verla>'}`} />
                        </li>
                        <li>
                            Commit + push. El <code className="bg-muted rounded px-1">php artisan migrate --force</code> del despliegue crea las
                            tablas <code className="bg-muted rounded px-1">nexus_buffer</code> y{' '}
                            <code className="bg-muted rounded px-1">nexus_blocks</code>.
                        </li>
                        <li>
                            Edita esta app en Nexus y deja vacía la “ruta de salud” para usar{' '}
                            <code className="bg-muted rounded px-1">/nexus/health</code>.
                        </li>
                    </ol>
                </Panel>
            )}
        </div>
    );
}

function CopyField({ value }: { value: string }) {
    const [copied, setCopied] = useState(false);

    return (
        <div className="bg-muted relative mt-2 rounded-md p-3 pr-12">
            <pre className="overflow-x-auto font-mono text-xs whitespace-pre-wrap">{value}</pre>
            <button
                className="hover:bg-background absolute top-2 right-2 rounded p-1.5"
                onClick={() => {
                    navigator.clipboard.writeText(value);
                    setCopied(true);
                    setTimeout(() => setCopied(false), 2000);
                }}
                aria-label="Copiar"
            >
                {copied ? <CheckCircle2 className="size-4" style={{ color: 'var(--status-good)' }} /> : <Copy className="size-4" />}
            </button>
        </div>
    );
}
