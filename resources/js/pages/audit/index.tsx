import { AuditDetail, auditSummary } from '@/components/audit-detail';
import { EmptyState, NativeSelect, PageHeader, Pagination, Panel, Table, applyFilters } from '@/components/nexus-ui';
import { ActionBadge, actionLabel } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime } from '@/lib/format';
import { type AppRef, type AuditLog, type Paginated } from '@/types';
import { Head } from '@inertiajs/react';
import { ChevronDown, ChevronRight, FileSpreadsheet, FileText, ScrollText, X } from 'lucide-react';
import { Fragment, useState } from 'react';

interface Filters {
    application_id?: string;
    user?: string;
    action?: string;
    module?: string;
    record_id?: string;
    from?: string;
    to?: string;
}

interface Props {
    logs: Paginated<AuditLog>;
    filters: Filters;
    applications: AppRef[];
    actions: string[];
    modules: string[];
}

export default function AuditIndex({ logs, filters, applications, actions, modules }: Props) {
    const [user, setUser] = useState(filters.user ?? '');
    const [recordId, setRecordId] = useState(filters.record_id ?? '');
    const [expanded, setExpanded] = useState<number | null>(null);
    const update = (patch: Filters) => applyFilters('/audit', { ...filters, ...patch });

    const query = new URLSearchParams(Object.entries(filters).filter(([, v]) => v) as [string, string][]).toString();
    const hasFilters = Object.values(filters).some(Boolean);

    return (
        <AppLayout breadcrumbs={[{ title: 'Auditoría', href: '/audit' }]}>
            <Head title="Auditoría" />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title="Auditoría de acciones"
                    description="Qué hizo cada usuario, sobre qué registro y qué valores cambiaron."
                    actions={
                        <>
                            <Button variant="outline" size="sm" asChild>
                                <a href={`/audit/export/xlsx${query ? `?${query}` : ''}`}>
                                    <FileSpreadsheet /> Excel
                                </a>
                            </Button>
                            <Button variant="outline" size="sm" asChild>
                                <a href={`/audit/export/pdf${query ? `?${query}` : ''}`}>
                                    <FileText /> PDF
                                </a>
                            </Button>
                        </>
                    }
                />

                <div className="flex flex-wrap gap-2">
                    <NativeSelect
                        value={filters.application_id ?? ''}
                        onChange={(e) => update({ application_id: e.target.value, module: '' })}
                        aria-label="Aplicación"
                    >
                        <option value="">Todas las apps</option>
                        {applications.map((a) => (
                            <option key={a.id} value={a.id}>
                                {a.name}
                            </option>
                        ))}
                    </NativeSelect>
                    <NativeSelect value={filters.action ?? ''} onChange={(e) => update({ action: e.target.value })} aria-label="Acción">
                        <option value="">Todas las acciones</option>
                        {actions.map((a) => (
                            <option key={a} value={a}>
                                {actionLabel(a)}
                            </option>
                        ))}
                    </NativeSelect>
                    <NativeSelect
                        value={filters.module ?? ''}
                        onChange={(e) => update({ module: e.target.value })}
                        aria-label="Módulo"
                        className="max-w-56"
                    >
                        <option value="">Todos los módulos</option>
                        {modules.map((m) => (
                            <option key={m} value={m}>
                                {m}
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
                    <form
                        className="flex flex-1 gap-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            update({ user, record_id: recordId });
                        }}
                    >
                        <Input
                            className="h-9 min-w-40 flex-1"
                            value={user}
                            onChange={(e) => setUser(e.target.value)}
                            placeholder="Usuario (nombre o ID)…"
                        />
                        <Input className="h-9 w-32" value={recordId} onChange={(e) => setRecordId(e.target.value)} placeholder="ID registro" />
                        <Button type="submit" size="sm" variant="secondary" className="h-9">
                            Buscar
                        </Button>
                    </form>
                    {hasFilters && (
                        <Button variant="ghost" size="sm" className="h-9" onClick={() => applyFilters('/audit', {})}>
                            <X /> Limpiar
                        </Button>
                    )}
                </div>

                <Panel>
                    {logs.data.length === 0 ? (
                        <EmptyState icon={ScrollText} title="Sin registros de auditoría">
                            Los registros llegan desde el agente Nexus instalado en cada aplicación.
                        </EmptyState>
                    ) : (
                        <Table className="-m-4">
                            <thead>
                                <tr>
                                    <th className="w-8" />
                                    <th>Fecha</th>
                                    <th>Usuario</th>
                                    <th>Acción</th>
                                    <th>Módulo</th>
                                    <th>Registro</th>
                                    <th>Aplicación</th>
                                    <th>IP</th>
                                </tr>
                            </thead>
                            <tbody>
                                {logs.data.map((log) => {
                                    const open = expanded === log.id;
                                    const summary = auditSummary(log);

                                    return (
                                        <Fragment key={log.id}>
                                            <tr className="hover:bg-accent/40 cursor-pointer" onClick={() => setExpanded(open ? null : log.id)}>
                                                <td className="text-muted-foreground">
                                                    {open ? <ChevronDown className="size-4" /> : <ChevronRight className="size-4" />}
                                                </td>
                                                <td className="text-xs whitespace-nowrap">{formatDateTime(log.occurred_at)}</td>
                                                <td>
                                                    {log.user_name ?? <span className="text-muted-foreground">Sistema</span>}
                                                    {log.external_user_id && (
                                                        <span className="text-muted-foreground ml-1 text-xs">#{log.external_user_id}</span>
                                                    )}
                                                </td>
                                                <td>
                                                    <ActionBadge action={log.action} />
                                                </td>
                                                <td className="text-xs">
                                                    {log.module}
                                                    {summary && <span className="text-muted-foreground"> · {summary}</span>}
                                                </td>
                                                <td className="font-mono text-xs">{log.record_id ?? '—'}</td>
                                                <td className="text-xs">{log.application?.name}</td>
                                                <td className="text-muted-foreground font-mono text-xs">{log.ip}</td>
                                            </tr>
                                            {open && (
                                                <tr>
                                                    <td colSpan={8} className="bg-muted/30">
                                                        <AuditDetail log={log} />
                                                        {log.url && (
                                                            <p className="text-muted-foreground mt-2 font-mono text-xs break-all">{log.url}</p>
                                                        )}
                                                    </td>
                                                </tr>
                                            )}
                                        </Fragment>
                                    );
                                })}
                            </tbody>
                        </Table>
                    )}
                </Panel>
                <Pagination meta={logs} />
            </div>
        </AppLayout>
    );
}
