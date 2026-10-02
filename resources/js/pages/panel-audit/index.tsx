import { EmptyState, NativeSelect, PageHeader, Pagination, Panel, Table, applyFilters } from '@/components/nexus-ui';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime } from '@/lib/format';
import { type AppRef, type Paginated } from '@/types';
import { Head } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';

interface Log {
    id: number;
    user: AppRef | null;
    action: string;
    description: string;
    properties: Record<string, unknown> | null;
    ip: string | null;
    user_agent: string | null;
    created_at: string;
}

interface Filters {
    user_id?: string;
    action?: string;
    from?: string;
    to?: string;
}

interface Props {
    logs: Paginated<Log>;
    filters: Filters;
    users: AppRef[];
    actionGroups: { value: string; label: string }[];
}

export default function PanelAuditIndex({ logs, filters, users, actionGroups }: Props) {
    const update = (patch: Filters) => applyFilters('/panel-audit', { ...filters, ...patch });

    return (
        <AppLayout breadcrumbs={[{ title: 'Auditoría del panel', href: '/panel-audit' }]}>
            <Head title="Auditoría del panel" />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title="Auditoría del panel"
                    description="Todo lo que se hace dentro de Nexus: accesos, bloqueos, cierres de sesión, cambios de configuración y exportaciones."
                />

                <div className="flex flex-wrap gap-2">
                    <NativeSelect value={filters.user_id ?? ''} onChange={(e) => update({ user_id: e.target.value })} aria-label="Usuario">
                        <option value="">Todos los usuarios</option>
                        {users.map((u) => (
                            <option key={u.id} value={u.id}>
                                {u.name}
                            </option>
                        ))}
                    </NativeSelect>
                    <NativeSelect value={filters.action ?? ''} onChange={(e) => update({ action: e.target.value })} aria-label="Tipo de acción">
                        <option value="">Todas las acciones</option>
                        {actionGroups.map((g) => (
                            <option key={g.value} value={g.value}>
                                {g.label}
                            </option>
                        ))}
                    </NativeSelect>
                    <Input
                        type="date"
                        className="h-9 w-auto"
                        value={filters.from ?? ''}
                        onChange={(e) => update({ from: e.target.value })}
                        aria-label="Desde"
                    />
                    <Input
                        type="date"
                        className="h-9 w-auto"
                        value={filters.to ?? ''}
                        onChange={(e) => update({ to: e.target.value })}
                        aria-label="Hasta"
                    />
                </div>

                <Panel>
                    {logs.data.length === 0 ? (
                        <EmptyState icon={ShieldCheck} title="Sin registros" />
                    ) : (
                        <Table className="-m-4">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Usuario</th>
                                    <th>Acción</th>
                                    <th>Detalle</th>
                                    <th>IP</th>
                                </tr>
                            </thead>
                            <tbody>
                                {logs.data.map((log) => (
                                    <tr key={log.id}>
                                        <td className="text-xs whitespace-nowrap">{formatDateTime(log.created_at)}</td>
                                        <td>{log.user?.name ?? <span className="text-muted-foreground">—</span>}</td>
                                        <td className="font-mono text-xs">{log.action}</td>
                                        <td className="text-sm">
                                            {log.description}
                                            {log.properties?.reason ? (
                                                <span className="text-muted-foreground"> · Motivo: {String(log.properties.reason)}</span>
                                            ) : null}
                                        </td>
                                        <td className="text-muted-foreground font-mono text-xs" title={log.user_agent ?? undefined}>
                                            {log.ip}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </Table>
                    )}
                </Panel>
                <Pagination meta={logs} />
            </div>
        </AppLayout>
    );
}
