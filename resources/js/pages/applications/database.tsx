import { EmptyState, PageHeader, Panel, StatCard, Table, applyFilters } from '@/components/nexus-ui';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import { Head, router } from '@inertiajs/react';
import { AlertTriangle, ChevronLeft, ChevronRight, Database, EyeOff, Lock, RefreshCw, Search, Table2 } from 'lucide-react';
import { useMemo, useState } from 'react';

interface TableInfo {
    name: string;
    rows: number;
    size_mb: number;
    engine: string | null;
    updated_at: string | null;
}

interface Overview {
    version: string;
    database: string;
    uptime_seconds: number | null;
    threads_connected: number | null;
    threads_running: number | null;
    max_connections: number;
    max_used_connections: number | null;
    slow_queries: number | null;
    aborted_connects: number | null;
    queries_per_second: number | null;
    size_mb: number;
    table_count: number;
    row_estimate: number;
    tables: TableInfo[];
}

interface Browse {
    table: string;
    columns: { name: string; type: string; sensitive: boolean }[];
    rows: Record<string, unknown>[];
    total: number;
    page: number;
    last_page: number;
}

interface Props {
    application: { id: number; name: string; database_service_name: string | null };
    overview: Overview | null;
    browse: Browse | null;
    filters: { table?: string | null; search?: string | null };
    error: string | null;
}

function formatUptime(seconds: number | null): string {
    if (!seconds) return '—';
    const days = Math.floor(seconds / 86400);
    const hours = Math.floor((seconds % 86400) / 3600);

    return days > 0 ? `${days} d ${hours} h` : `${hours} h`;
}

export default function DatabasePage({ application: app, overview, browse, filters, error }: Props) {
    const [tableFilter, setTableFilter] = useState('');
    const [search, setSearch] = useState(filters.search ?? '');
    const url = `/applications/${app.id}/database`;

    const tables = useMemo(
        () => (overview?.tables ?? []).filter((t) => t.name.toLowerCase().includes(tableFilter.toLowerCase())),
        [overview, tableFilter],
    );

    const openTable = (table: string | null, extra: Record<string, string | number | undefined> = {}) =>
        applyFilters(url, { table: table ?? undefined, ...extra });

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Aplicaciones', href: '/applications' },
                { title: app.name, href: `/applications/${app.id}` },
                { title: 'Base de datos', href: url },
            ]}
        >
            <Head title={`Base de datos · ${app.name}`} />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title={`Base de datos de ${app.name}`}
                    description={
                        <span className="inline-flex items-center gap-1.5">
                            <Lock className="size-3.5" /> Conexión de solo lectura a {app.database_service_name ?? 'MySQL'} en Railway. Cada consulta
                            queda en la auditoría del panel.
                        </span>
                    }
                    actions={
                        <Button variant="outline" size="sm" onClick={() => router.get(url, { ...filters, refresh: 1 }, { preserveScroll: true })}>
                            <RefreshCw /> Actualizar
                        </Button>
                    }
                />

                {error && (
                    <div className="flex items-start gap-2 rounded-lg border p-3 text-sm" style={{ borderColor: 'var(--status-critical)' }}>
                        <AlertTriangle className="mt-0.5 size-4 shrink-0" style={{ color: 'var(--status-critical)' }} />
                        <div>
                            <p className="font-medium">No se pudo consultar la base de datos</p>
                            <p className="text-muted-foreground">{error}</p>
                        </div>
                    </div>
                )}

                {overview && (
                    <>
                        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                            <StatCard
                                label="Tamaño"
                                value={`${formatNumber(overview.size_mb)} MB`}
                                hint={`${overview.table_count} tablas · ~${formatNumber(overview.row_estimate)} filas`}
                                icon={Database}
                            />
                            <StatCard
                                label="Conexiones"
                                value={`${overview.threads_connected ?? '—'} / ${overview.max_connections}`}
                                hint={`Máximo usado: ${overview.max_used_connections ?? '—'} · activas: ${overview.threads_running ?? '—'}`}
                            />
                            <StatCard
                                label="Consultas por segundo"
                                value={overview.queries_per_second ?? '—'}
                                hint={`Consultas lentas: ${formatNumber(overview.slow_queries)}`}
                            />
                            <StatCard
                                label="MySQL"
                                value={overview.version}
                                hint={`Encendido hace ${formatUptime(overview.uptime_seconds)} · BD ${overview.database}`}
                            />
                        </div>

                        <div className="grid gap-4 lg:grid-cols-[18rem_1fr]">
                            <Panel title="Tablas" className="h-fit">
                                <Input
                                    className="mb-2 h-8"
                                    value={tableFilter}
                                    onChange={(e) => setTableFilter(e.target.value)}
                                    placeholder="Filtrar tablas…"
                                />
                                <ul className="-mx-2 max-h-[60vh] overflow-y-auto text-sm">
                                    {tables.map((t) => (
                                        <li key={t.name}>
                                            <button
                                                onClick={() => openTable(t.name)}
                                                className={cn(
                                                    'hover:bg-accent flex w-full items-center justify-between gap-2 rounded-md px-2 py-1.5 text-left',
                                                    filters.table === t.name && 'bg-accent font-medium',
                                                )}
                                            >
                                                <span className="truncate font-mono text-xs">{t.name}</span>
                                                <span className="text-muted-foreground shrink-0 text-[11px] tabular-nums">
                                                    {formatNumber(t.rows)} · {t.size_mb} MB
                                                </span>
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            </Panel>

                            <Panel
                                title={browse ? <span className="font-mono">{browse.table}</span> : 'Explorador'}
                                description={browse ? `${formatNumber(browse.total)} registros · más recientes primero` : undefined}
                                actions={
                                    browse && (
                                        <form
                                            className="flex gap-2"
                                            onSubmit={(e) => {
                                                e.preventDefault();
                                                openTable(browse.table, { search });
                                            }}
                                        >
                                            <Input
                                                className="h-8 w-56"
                                                value={search}
                                                onChange={(e) => setSearch(e.target.value)}
                                                placeholder="Buscar en el texto…"
                                            />
                                            <Button type="submit" size="sm" variant="secondary" className="h-8">
                                                <Search />
                                            </Button>
                                        </form>
                                    )
                                }
                            >
                                {!browse ? (
                                    <EmptyState icon={Table2} title="Selecciona una tabla">
                                        Las columnas con contraseñas, tokens o secretos nunca se muestran.
                                    </EmptyState>
                                ) : browse.rows.length === 0 ? (
                                    <EmptyState icon={Table2} title="Sin registros" />
                                ) : (
                                    <>
                                        <Table className="-mx-4 max-h-[60vh] overflow-auto">
                                            <thead className="bg-card sticky top-0">
                                                <tr>
                                                    {browse.columns.map((c) => (
                                                        <th key={c.name} title={c.type}>
                                                            <span className="inline-flex items-center gap-1 font-mono">
                                                                {c.sensitive && <EyeOff className="size-3" />}
                                                                {c.name}
                                                            </span>
                                                        </th>
                                                    ))}
                                                </tr>
                                            </thead>
                                            <tbody className="font-mono text-xs">
                                                {browse.rows.map((row, i) => (
                                                    <tr key={i}>
                                                        {browse.columns.map((c) => (
                                                            <td
                                                                key={c.name}
                                                                className="max-w-xs truncate whitespace-nowrap"
                                                                title={row[c.name] === null ? '' : String(row[c.name])}
                                                            >
                                                                {row[c.name] === null ? (
                                                                    <span className="text-muted-foreground">∅</span>
                                                                ) : (
                                                                    String(row[c.name])
                                                                )}
                                                            </td>
                                                        ))}
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </Table>
                                        <div className="mt-3 flex items-center justify-end gap-2">
                                            <Button
                                                variant="outline"
                                                size="icon"
                                                disabled={browse.page <= 1}
                                                onClick={() =>
                                                    openTable(browse.table, { search: filters.search ?? undefined, page: browse.page - 1 })
                                                }
                                                aria-label="Anterior"
                                            >
                                                <ChevronLeft />
                                            </Button>
                                            <span className="text-muted-foreground text-xs">
                                                Página {browse.page} de {browse.last_page}
                                            </span>
                                            <Button
                                                variant="outline"
                                                size="icon"
                                                disabled={browse.page >= browse.last_page}
                                                onClick={() =>
                                                    openTable(browse.table, { search: filters.search ?? undefined, page: browse.page + 1 })
                                                }
                                                aria-label="Siguiente"
                                            >
                                                <ChevronRight />
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Panel>
                        </div>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
