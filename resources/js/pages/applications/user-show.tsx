import { AuditDetail, auditSummary } from '@/components/audit-detail';
import { TimeChart } from '@/components/charts';
import { EmptyState, PageHeader, Pagination, Panel, StatCard, Table } from '@/components/nexus-ui';
import { ActionBadge, LoginEventBadge, actionLabel } from '@/components/status-badge';
import { UnblockButton, UserActionsMenu } from '@/components/user-action-dialog';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime, formatNumber, formatRelative } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type AppSession, type AuditLog, type LoginEvent, type Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Activity, Ban, Bug, ChevronDown, ChevronRight, KeyRound, ScrollText } from 'lucide-react';
import { Fragment, useState } from 'react';

interface Profile {
    id: string;
    name: string;
    email: string | null;
    document: string | null;
    roles: string[];
    is_active: boolean | null;
    deleted: boolean;
    created_at: string | null;
    last_login_at: string | null;
    online: boolean;
    raw?: Record<string, unknown>;
}

interface Props {
    application: { id: number; name: string };
    externalId: string;
    name: string;
    profile: Profile | null;
    profileError: string | null;
    canOperate: boolean;
    blocked: { executed_at: string; payload: { minutes?: number; reason?: string } | null } | null;
    sessions: AppSession[];
    stats: { logins_30d: number; failed_30d: number; actions_30d: Record<string, number>; errors_30d: number };
    activity: { date: string; requests: number }[];
    topRoutes: Record<string, number>;
    logins: LoginEvent[];
    audits: Paginated<AuditLog>;
    actionFilter: string | null;
    errors: {
        id: number;
        error_group_id: number;
        url: string | null;
        occurred_at: string;
        group: { exception_class: string; message: string } | null;
    }[];
}

export default function UserShow(props: Props) {
    const { application: app, externalId, name, profile, canOperate, blocked, stats } = props;
    const [expanded, setExpanded] = useState<number | null>(null);
    const [showRaw, setShowRaw] = useState(false);
    const target = { applicationId: app.id, applicationName: app.name, externalUserId: externalId, name };
    const totalActions = Object.values(stats.actions_30d).reduce((a, b) => a + Number(b), 0);
    const routes = Object.entries(props.topRoutes);
    const maxRoute = Math.max(1, ...routes.map(([, n]) => n));

    const filterAction = (action: string | null) =>
        router.get(`/applications/${app.id}/users/${externalId}`, action ? { action } : {}, {
            preserveScroll: true,
            preserveState: true,
            only: ['audits', 'actionFilter'],
        });

    return (
        <AppLayout
            breadcrumbs={[
                { title: app.name, href: `/applications/${app.id}` },
                { title: 'Usuarios', href: `/applications/${app.id}/users` },
                { title: name, href: '#' },
            ]}
        >
            <Head title={`${name} · ${app.name}`} />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title={name}
                    description={
                        <span className="flex flex-wrap items-center gap-x-3 gap-y-1">
                            <span>
                                {app.name} · ID {externalId}
                            </span>
                            {profile?.email && <span>{profile.email}</span>}
                            {profile?.document && <span className="font-mono">{profile.document}</span>}
                            {profile?.roles.length ? <span>{profile.roles.join(', ')}</span> : null}
                            {props.sessions.length > 0 && (
                                <span className="inline-flex items-center gap-1.5">
                                    <span className="size-2 rounded-full" style={{ background: 'var(--status-good)' }} /> Conectado ahora
                                </span>
                            )}
                            {profile?.is_active === false && <span>Cuenta inactiva en la app</span>}
                        </span>
                    }
                    actions={canOperate && (blocked ? <UnblockButton target={target} /> : <UserActionsMenu target={target} />)}
                />

                {blocked && (
                    <div className="flex items-center gap-2 rounded-lg border p-3 text-sm" style={{ borderColor: 'var(--status-critical)' }}>
                        <Ban className="size-4" style={{ color: 'var(--status-critical)' }} />
                        Bloqueado desde Nexus el {formatDateTime(blocked.executed_at)}
                        {blocked.payload?.minutes ? ` por ${blocked.payload.minutes} minutos` : ' indefinidamente'}
                        {blocked.payload?.reason && ` · Motivo: ${blocked.payload.reason}`}
                    </div>
                )}

                {props.profileError && (
                    <p className="text-muted-foreground text-xs">No se pudo leer el perfil desde la base de datos: {props.profileError}</p>
                )}

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <StatCard
                        label="Ingresos (30 días)"
                        value={formatNumber(stats.logins_30d)}
                        hint={profile?.last_login_at ? `Último ${formatRelative(profile.last_login_at)}` : undefined}
                        icon={KeyRound}
                    />
                    <StatCard
                        label="Intentos fallidos (30 días)"
                        value={formatNumber(stats.failed_30d)}
                        icon={KeyRound}
                        tone={stats.failed_30d > 0 ? 'var(--status-critical)' : undefined}
                    />
                    <StatCard
                        label="Acciones (30 días)"
                        value={formatNumber(totalActions)}
                        hint={
                            Object.entries(stats.actions_30d)
                                .map(([a, n]) => `${actionLabel(a)} ${n}`)
                                .join(' · ') || undefined
                        }
                        icon={ScrollText}
                    />
                    <StatCard
                        label="Errores que le ocurrieron"
                        value={formatNumber(stats.errors_30d)}
                        icon={Bug}
                        tone={stats.errors_30d > 0 ? 'var(--status-serious)' : undefined}
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Panel title="Actividad diaria" description="Peticiones hechas en la app · últimos 30 días" className="lg:col-span-2">
                        <TimeChart
                            data={props.activity}
                            xKey="date"
                            kind="bar"
                            series={[{ key: 'requests', label: 'Peticiones', color: 'var(--series-1)' }]}
                            format={(v) => formatNumber(v)}
                            height={200}
                        />
                    </Panel>
                    <Panel title="Lo que más usa" description="Rutas más visitadas · 30 días">
                        {routes.length === 0 ? (
                            <EmptyState icon={Activity} title="Sin actividad registrada" />
                        ) : (
                            <ul className="space-y-2">
                                {routes.map(([route, count]) => (
                                    <li key={route} className="text-xs">
                                        <div className="flex justify-between gap-2">
                                            <span className="truncate font-mono" title={route}>
                                                {route}
                                            </span>
                                            <span className="text-muted-foreground shrink-0 tabular-nums">{count}</span>
                                        </div>
                                        <div className="bg-muted mt-1 h-1 rounded-full">
                                            <div
                                                className="h-1 rounded-full"
                                                style={{ width: `${(count / maxRoute) * 100}%`, background: 'var(--series-1)' }}
                                            />
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>
                </div>

                <Panel
                    title="Qué ha hecho"
                    description="Creaciones, ediciones, eliminaciones, archivos subidos y descargados"
                    actions={
                        <div className="flex flex-wrap gap-1">
                            {[null, 'created', 'updated', 'deleted', 'uploaded', 'downloaded'].map((action) => (
                                <button
                                    key={action ?? 'all'}
                                    onClick={() => filterAction(action)}
                                    className={cn(
                                        'rounded-full border px-2.5 py-0.5 text-xs',
                                        (props.actionFilter ?? null) === action
                                            ? 'bg-primary text-primary-foreground border-primary'
                                            : 'hover:bg-accent',
                                    )}
                                >
                                    {action ? actionLabel(action) : 'Todo'}
                                </button>
                            ))}
                        </div>
                    }
                >
                    {props.audits.data.length === 0 ? (
                        <EmptyState icon={ScrollText} title="Sin acciones registradas" />
                    ) : (
                        <Table className="-m-4">
                            <tbody>
                                {props.audits.data.map((log) => {
                                    const open = expanded === log.id;
                                    const summary = auditSummary(log);

                                    return (
                                        <Fragment key={log.id}>
                                            <tr className="hover:bg-accent/40 cursor-pointer" onClick={() => setExpanded(open ? null : log.id)}>
                                                <td className="text-muted-foreground w-6">
                                                    {open ? <ChevronDown className="size-4" /> : <ChevronRight className="size-4" />}
                                                </td>
                                                <td className="w-32 text-xs whitespace-nowrap">{formatDateTime(log.occurred_at)}</td>
                                                <td className="w-32">
                                                    <ActionBadge action={log.action} />
                                                </td>
                                                <td className="text-sm">
                                                    {log.module}
                                                    {log.record_id && <span className="text-muted-foreground"> #{log.record_id}</span>}
                                                    {summary && <span className="text-muted-foreground text-xs"> · {summary}</span>}
                                                </td>
                                                <td className="text-muted-foreground text-right font-mono text-xs">{log.ip}</td>
                                            </tr>
                                            {open && (
                                                <tr>
                                                    <td colSpan={5} className="bg-muted/30">
                                                        <AuditDetail log={log} />
                                                    </td>
                                                </tr>
                                            )}
                                        </Fragment>
                                    );
                                })}
                            </tbody>
                        </Table>
                    )}
                    <Pagination meta={props.audits} />
                </Panel>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Panel title="Inicios de sesión">
                        {props.logins.length === 0 ? (
                            <EmptyState icon={KeyRound} title="Sin registros" />
                        ) : (
                            <ul className="-my-1 divide-y text-sm">
                                {props.logins.map((l) => (
                                    <li key={l.id} className="flex items-center gap-3 py-1.5">
                                        <span className="w-24 shrink-0">
                                            <LoginEventBadge event={l.event} />
                                        </span>
                                        <span className="text-muted-foreground truncate text-xs">
                                            {l.ip} · {l.device ?? '—'}
                                        </span>
                                        <span className="text-muted-foreground ml-auto shrink-0 text-xs">{formatDateTime(l.occurred_at)}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>
                    <Panel title="Errores que le ocurrieron">
                        {props.errors.length === 0 ? (
                            <EmptyState icon={Bug} title="Ningún error" />
                        ) : (
                            <ul className="-my-1 divide-y text-sm">
                                {props.errors.map((e) => (
                                    <li key={e.id}>
                                        <Link href={`/errors/${e.error_group_id}`} className="hover:bg-accent/40 -mx-2 block rounded px-2 py-1.5">
                                            <div className="flex justify-between gap-2">
                                                <span className="truncate font-medium">{e.group?.exception_class.split('\\').pop()}</span>
                                                <span className="text-muted-foreground shrink-0 text-xs">{formatRelative(e.occurred_at)}</span>
                                            </div>
                                            <p className="text-muted-foreground truncate text-xs">{e.group?.message}</p>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>
                </div>

                {profile?.raw && (
                    <Panel
                        title="Registro en la base de datos"
                        description="Solo superadministrador · campos sensibles ocultos"
                        actions={
                            <button className="text-muted-foreground text-xs hover:underline" onClick={() => setShowRaw(!showRaw)}>
                                {showRaw ? 'Ocultar' : 'Mostrar'}
                            </button>
                        }
                    >
                        {showRaw ? (
                            <dl className="grid gap-x-6 gap-y-1 text-xs sm:grid-cols-2">
                                {Object.entries(profile.raw).map(([key, value]) => (
                                    <div key={key} className="flex gap-2 border-b py-1">
                                        <dt className="text-muted-foreground w-40 shrink-0 font-mono">{key}</dt>
                                        <dd className="break-all">{value === null ? '∅' : String(value)}</dd>
                                    </div>
                                ))}
                            </dl>
                        ) : (
                            <p className="text-muted-foreground text-xs">{Object.keys(profile.raw).length} columnas</p>
                        )}
                    </Panel>
                )}
            </div>
        </AppLayout>
    );
}
