import { EmptyState, NativeSelect, PageHeader, Pagination, Panel, applyFilters } from '@/components/nexus-ui';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { formatNumber, formatRelative } from '@/lib/format';
import { type AppRef, type ErrorGroup, type Paginated } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Bug, CheckCircle2 } from 'lucide-react';
import { useState } from 'react';

interface Props {
    groups: Paginated<ErrorGroup>;
    filters: { application_id?: string; status: string; search?: string };
    applications: AppRef[];
}

export default function ErrorsIndex({ groups, filters, applications }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const update = (patch: Partial<Props['filters']>) => applyFilters('/errors', { ...filters, ...patch });

    return (
        <AppLayout breadcrumbs={[{ title: 'Errores', href: '/errors' }]}>
            <Head title="Errores" />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader title="Errores y excepciones" description="Las excepciones repetidas se agrupan por clase, archivo y línea." />

                <div className="flex flex-wrap gap-2">
                    <NativeSelect
                        value={filters.application_id ?? ''}
                        onChange={(e) => update({ application_id: e.target.value })}
                        aria-label="Aplicación"
                    >
                        <option value="">Todas las apps</option>
                        {applications.map((a) => (
                            <option key={a.id} value={a.id}>
                                {a.name}
                            </option>
                        ))}
                    </NativeSelect>
                    <NativeSelect value={filters.status} onChange={(e) => update({ status: e.target.value })} aria-label="Estado">
                        <option value="open">Sin resolver</option>
                        <option value="resolved">Resueltos</option>
                        <option value="all">Todos</option>
                    </NativeSelect>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            update({ search });
                        }}
                        className="min-w-48 flex-1"
                    >
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Buscar por mensaje, clase o archivo…"
                            className="h-9"
                        />
                    </form>
                </div>

                <Panel className="overflow-hidden">
                    {groups.data.length === 0 ? (
                        <EmptyState
                            icon={filters.status === 'open' ? CheckCircle2 : Bug}
                            title={filters.status === 'open' ? 'No hay errores sin resolver' : 'Sin resultados'}
                        />
                    ) : (
                        <ul className="-m-4 divide-y">
                            {groups.data.map((g) => (
                                <li key={g.id}>
                                    <Link href={`/errors/${g.id}`} className="hover:bg-accent/40 flex gap-4 px-4 py-3">
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="font-medium">{g.exception_class.split('\\').pop()}</span>
                                                <span className="text-muted-foreground text-xs">{g.application?.name}</span>
                                                {g.resolved_at && (
                                                    <span className="inline-flex items-center gap-1 text-xs">
                                                        <CheckCircle2 className="size-3.5" style={{ color: 'var(--status-good)' }} /> Resuelto
                                                    </span>
                                                )}
                                            </div>
                                            <p className="truncate text-sm">{g.message}</p>
                                            <p className="text-muted-foreground truncate font-mono text-xs">
                                                {g.file}
                                                {g.line ? `:${g.line}` : ''}
                                            </p>
                                        </div>
                                        <div className="shrink-0 text-right text-xs">
                                            <div className="text-base font-semibold tabular-nums">{formatNumber(g.occurrences)}</div>
                                            <div className="text-muted-foreground">veces · {g.last_24h ?? 0} en 24 h</div>
                                            <div className="text-muted-foreground">{formatRelative(g.last_seen_at)}</div>
                                        </div>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>
                <Pagination meta={groups} />
            </div>
        </AppLayout>
    );
}
