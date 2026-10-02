import { EmptyState, NativeSelect, PageHeader, Panel, Table, applyFilters } from '@/components/nexus-ui';
import { UnblockButton, UserActionsMenu } from '@/components/user-action-dialog';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime, formatRelative } from '@/lib/format';
import { type AppRef, type AppSession } from '@/types';
import { Head, usePoll } from '@inertiajs/react';
import { Ban, UsersRound } from 'lucide-react';

interface Block {
    application: AppRef;
    external_user_id: string;
    target_name: string | null;
    blocked_at: string;
    blocked_by: string | null;
    minutes: number | null;
    reason: string | null;
}

interface Props {
    sessions: AppSession[];
    blocks: Block[];
    applications: (AppRef & { last_ingest_at: string | null })[];
    operableIds: number[];
    filters: { application_id?: string | null };
    refreshedAt: string;
}

export default function SessionsIndex({ sessions, blocks, applications, operableIds, filters, refreshedAt }: Props) {
    usePoll(15_000, {}, { keepAlive: false });

    const byApp = applications.map((app) => ({ app, rows: sessions.filter((s) => s.application_id === app.id) })).filter((g) => g.rows.length > 0);

    return (
        <AppLayout breadcrumbs={[{ title: 'Conectados ahora', href: '/sessions' }]}>
            <Head title="Conectados ahora" />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title="Usuarios conectados ahora"
                    description={
                        <span className="inline-flex items-center gap-2">
                            <span className="relative flex size-2">
                                <span
                                    className="absolute inline-flex size-full animate-ping rounded-full opacity-60"
                                    style={{ background: 'var(--status-good)' }}
                                />
                                <span className="relative inline-flex size-2 rounded-full" style={{ background: 'var(--status-good)' }} />
                            </span>
                            {sessions.length} usuarios con actividad en los últimos 15 min · actualizado {formatRelative(refreshedAt)}
                        </span>
                    }
                    actions={
                        <NativeSelect
                            value={filters.application_id ?? ''}
                            onChange={(e) => applyFilters('/sessions', { application_id: e.target.value })}
                            aria-label="Aplicación"
                        >
                            <option value="">Todas las apps</option>
                            {applications.map((a) => (
                                <option key={a.id} value={a.id}>
                                    {a.name}
                                </option>
                            ))}
                        </NativeSelect>
                    }
                />

                {byApp.length === 0 ? (
                    <Panel>
                        <EmptyState icon={UsersRound} title="No hay usuarios conectados">
                            Los datos llegan desde el agente Nexus de cada app aproximadamente cada minuto.
                        </EmptyState>
                    </Panel>
                ) : (
                    byApp.map(({ app, rows }) => (
                        <Panel key={app.id} title={app.name} description={`${rows.length} conectados`}>
                            <Table>
                                <thead>
                                    <tr>
                                        <th>Usuario</th>
                                        <th>Rol</th>
                                        <th>Inicio de sesión</th>
                                        <th>Última actividad</th>
                                        <th>Dispositivo</th>
                                        <th>IP</th>
                                        <th className="w-10" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.map((s) => (
                                        <tr key={s.id}>
                                            <td>
                                                <div className="font-medium">{s.user_name ?? `#${s.external_user_id}`}</div>
                                                <div className="text-muted-foreground text-xs">{s.user_email}</div>
                                            </td>
                                            <td className="text-xs">{s.user_role ?? '—'}</td>
                                            <td className="text-xs whitespace-nowrap">{formatDateTime(s.login_at)}</td>
                                            <td className="text-xs whitespace-nowrap">{formatRelative(s.last_activity_at)}</td>
                                            <td className="text-muted-foreground text-xs">{s.device ?? '—'}</td>
                                            <td className="text-muted-foreground text-xs">{s.ip}</td>
                                            <td className="text-right">
                                                {operableIds.includes(app.id) && (
                                                    <UserActionsMenu
                                                        target={{
                                                            applicationId: app.id,
                                                            applicationName: app.name,
                                                            externalUserId: s.external_user_id,
                                                            name: s.user_name,
                                                        }}
                                                    />
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </Table>
                        </Panel>
                    ))
                )}

                {blocks.length > 0 && (
                    <Panel
                        title={
                            <span className="inline-flex items-center gap-2">
                                <Ban className="size-4" /> Usuarios bloqueados
                            </span>
                        }
                    >
                        <Table>
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Aplicación</th>
                                    <th>Bloqueado</th>
                                    <th>Duración</th>
                                    <th>Motivo</th>
                                    <th />
                                </tr>
                            </thead>
                            <tbody>
                                {blocks.map((b) => (
                                    <tr key={`${b.application.id}-${b.external_user_id}`}>
                                        <td className="font-medium">{b.target_name ?? `#${b.external_user_id}`}</td>
                                        <td>{b.application.name}</td>
                                        <td className="text-xs">
                                            {formatDateTime(b.blocked_at)}
                                            {b.blocked_by && <div className="text-muted-foreground">por {b.blocked_by}</div>}
                                        </td>
                                        <td className="text-xs">{b.minutes ? `${b.minutes} min` : 'Indefinido'}</td>
                                        <td className="text-muted-foreground text-xs">{b.reason ?? '—'}</td>
                                        <td className="text-right">
                                            {operableIds.includes(b.application.id) && (
                                                <UnblockButton
                                                    target={{
                                                        applicationId: b.application.id,
                                                        applicationName: b.application.name,
                                                        externalUserId: b.external_user_id,
                                                        name: b.target_name,
                                                    }}
                                                />
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </Table>
                    </Panel>
                )}
            </div>
        </AppLayout>
    );
}
