import { TimeChart } from '@/components/charts';
import { PageHeader, Pagination, Panel, StatCard, Table } from '@/components/nexus-ui';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime, formatNumber, formatRelative } from '@/lib/format';
import { type AppRef, type ErrorGroup, type Paginated } from '@/types';
import { Head, router } from '@inertiajs/react';
import { CheckCircle2, RotateCcw } from 'lucide-react';
import { Fragment, useState } from 'react';

interface ErrorEvent {
    id: number;
    external_user_id: string | null;
    user_name: string | null;
    url: string | null;
    method: string | null;
    ip: string | null;
    trace: string | null;
    occurred_at: string;
}

interface Props {
    group: ErrorGroup & { application: AppRef; resolver: AppRef | null };
    events: Paginated<ErrorEvent>;
    affectedUsers: { external_user_id: string; user_name: string | null; total: number; last_at: string }[];
    daily: { date: string; total: number }[];
    canOperate: boolean;
}

export default function ErrorShow({ group, events, affectedUsers, daily, canOperate }: Props) {
    const [openTrace, setOpenTrace] = useState<number | null>(events.data[0]?.id ?? null);

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Errores', href: '/errors' },
                { title: group.exception_class.split('\\').pop() ?? 'Error', href: '#' },
            ]}
        >
            <Head title="Detalle de error" />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title={group.exception_class}
                    description={
                        <>
                            {group.application.name} ·{' '}
                            {group.resolved_at ? `Resuelto ${formatRelative(group.resolved_at)} por ${group.resolver?.name ?? '—'}` : 'Sin resolver'}
                        </>
                    }
                    actions={
                        canOperate && (
                            <Button
                                variant={group.resolved_at ? 'outline' : 'default'}
                                onClick={() => router.post(`/errors/${group.id}/resolve`, {}, { preserveScroll: true })}
                            >
                                {group.resolved_at ? <RotateCcw /> : <CheckCircle2 />}
                                {group.resolved_at ? 'Reabrir' : 'Marcar como resuelto'}
                            </Button>
                        )
                    }
                />

                <Panel>
                    <p className="font-medium break-words">{group.message}</p>
                    <p className="text-muted-foreground mt-1 font-mono text-xs break-all">
                        {group.file}
                        {group.line ? `:${group.line}` : ''}
                    </p>
                </Panel>

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <StatCard label="Ocurrencias" value={formatNumber(group.occurrences)} />
                    <StatCard label="Usuarios afectados" value={affectedUsers.length} />
                    <StatCard label="Primera vez" value={formatRelative(group.first_seen_at)} hint={formatDateTime(group.first_seen_at)} />
                    <StatCard label="Última vez" value={formatRelative(group.last_seen_at)} hint={formatDateTime(group.last_seen_at)} />
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Panel title="Frecuencia diaria" description="Últimos 14 días" className="lg:col-span-2">
                        <TimeChart
                            data={daily}
                            xKey="date"
                            kind="bar"
                            series={[{ key: 'total', label: 'Ocurrencias', color: 'var(--series-1)' }]}
                            format={(v) => formatNumber(v)}
                            height={180}
                        />
                    </Panel>
                    <Panel title="Usuarios afectados">
                        {affectedUsers.length === 0 ? (
                            <p className="text-muted-foreground text-sm">Ocurrió sin usuario autenticado.</p>
                        ) : (
                            <ul className="space-y-2 text-sm">
                                {affectedUsers.map((u) => (
                                    <li key={u.external_user_id} className="flex justify-between gap-2">
                                        <span className="truncate">{u.user_name ?? `#${u.external_user_id}`}</span>
                                        <span className="text-muted-foreground shrink-0 text-xs">{u.total}×</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>
                </div>

                <Panel title="Ocurrencias">
                    <Table>
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Usuario</th>
                                <th>Petición</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            {events.data.map((e) => (
                                <Fragment key={e.id}>
                                    <tr className="hover:bg-accent/40 cursor-pointer" onClick={() => setOpenTrace(openTrace === e.id ? null : e.id)}>
                                        <td className="text-xs whitespace-nowrap">{formatDateTime(e.occurred_at)}</td>
                                        <td>{e.user_name ?? (e.external_user_id ? `#${e.external_user_id}` : '—')}</td>
                                        <td className="max-w-md truncate font-mono text-xs">
                                            {e.method} {e.url}
                                        </td>
                                        <td className="text-muted-foreground text-xs">{e.ip}</td>
                                    </tr>
                                    {openTrace === e.id && e.trace && (
                                        <tr>
                                            <td colSpan={4} className="bg-muted/40">
                                                <pre className="max-h-80 overflow-auto font-mono text-[11px] leading-relaxed whitespace-pre-wrap">
                                                    {e.trace}
                                                </pre>
                                            </td>
                                        </tr>
                                    )}
                                </Fragment>
                            ))}
                        </tbody>
                    </Table>
                    <Pagination meta={events} />
                </Panel>
            </div>
        </AppLayout>
    );
}
