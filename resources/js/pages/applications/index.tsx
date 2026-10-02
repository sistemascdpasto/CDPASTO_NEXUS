import { Meter } from '@/components/charts';
import { EmptyState, PageHeader, applyFilters } from '@/components/nexus-ui';
import { StatusBadge, statusColor } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { HealthRing } from '@/components/viz';
import AppLayout from '@/layouts/app-layout';
import { formatMs, formatRelative } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type AppStatus, type SharedData } from '@/types';
import { Head, Link, usePage, usePoll } from '@inertiajs/react';
import { Activity, ExternalLink, Plus } from 'lucide-react';

interface AppRow {
    id: number;
    name: string;
    type: string;
    url: string;
    environment: string;
    is_active: boolean;
    status: AppStatus;
    last_status_code: number | null;
    last_response_ms: number | null;
    last_checked_at: string | null;
    status_changed_at: string | null;
    railway_service_id: string | null;
    open_errors: number;
    active_users: number;
    uptime_24h: number | null;
    score: number | null;
    grade: string | null;
}

const filters: { value: AppStatus | ''; label: string }[] = [
    { value: '', label: 'Todas' },
    { value: 'online', label: 'En línea' },
    { value: 'degraded', label: 'Degradadas' },
    { value: 'down', label: 'Caídas' },
    { value: 'unknown', label: 'Sin datos' },
];

export default function ApplicationsIndex({
    applications,
    filters: current,
    counts,
}: {
    applications: AppRow[];
    filters: { status?: string };
    counts: Record<string, number>;
}) {
    const { auth } = usePage<SharedData>().props;
    usePoll(30_000, {}, { keepAlive: false });

    return (
        <AppLayout breadcrumbs={[{ title: 'Aplicaciones', href: '/applications' }]}>
            <Head title="Aplicaciones" />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title="Aplicaciones"
                    description="Estado de cada sistema desplegado. Se actualiza automáticamente."
                    actions={
                        auth.user.is_superadmin && (
                            <Button asChild>
                                <Link href="/applications/create">
                                    <Plus /> Registrar aplicación
                                </Link>
                            </Button>
                        )
                    }
                />

                <div className="flex flex-wrap gap-1.5" role="tablist" aria-label="Filtrar por estado">
                    {filters.map((f) => {
                        const active = (current.status ?? '') === f.value;
                        const count = f.value ? (counts[f.value] ?? 0) : Object.values(counts).reduce((a, b) => a + Number(b), 0);

                        return (
                            <button
                                key={f.value}
                                role="tab"
                                aria-selected={active}
                                onClick={() => applyFilters('/applications', { status: f.value })}
                                className={cn(
                                    'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-sm',
                                    active ? 'bg-primary text-primary-foreground border-primary' : 'hover:bg-accent',
                                )}
                            >
                                {f.value && <span className="size-2 rounded-full" style={{ background: statusColor(f.value) }} />}
                                {f.label}
                                <span className={cn('text-xs', active ? 'opacity-80' : 'text-muted-foreground')}>{count}</span>
                            </button>
                        );
                    })}
                </div>

                {applications.length === 0 ? (
                    <EmptyState icon={Activity} title="No hay aplicaciones con este estado" />
                ) : (
                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {applications.map((app) => (
                            <Link
                                key={app.id}
                                href={`/applications/${app.id}`}
                                prefetch
                                className={cn(
                                    'bg-card group flex flex-col gap-3 rounded-xl border p-4 transition-colors hover:border-neutral-300 dark:hover:border-neutral-700',
                                    !app.is_active && 'opacity-60',
                                )}
                                style={{ borderLeftWidth: 4, borderLeftColor: statusColor(app.is_active ? app.status : 'unknown') }}
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <HealthRing score={app.is_active ? app.score : null} size={46} stroke={4.5} />
                                    <div className="min-w-0 flex-1">
                                        <h2 className="truncate font-semibold">{app.name}</h2>
                                        <p className="text-muted-foreground flex items-center gap-1 truncate text-xs">
                                            {app.url.replace(/^https?:\/\//, '')}
                                            <ExternalLink className="size-3 opacity-0 group-hover:opacity-100" />
                                        </p>
                                    </div>
                                    {app.is_active ? (
                                        <StatusBadge status={app.status} />
                                    ) : (
                                        <span className="text-muted-foreground text-xs">Desactivada</span>
                                    )}
                                </div>

                                <dl className="grid grid-cols-3 gap-2 text-xs">
                                    <div>
                                        <dt className="text-muted-foreground">Respuesta</dt>
                                        <dd className="font-medium tabular-nums">{formatMs(app.last_response_ms)}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">Usuarios activos</dt>
                                        <dd className="font-medium">{app.type === 'static' ? '—' : app.active_users}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">Errores abiertos</dt>
                                        <dd className="font-medium" style={app.open_errors > 0 ? { color: 'var(--status-critical)' } : undefined}>
                                            {app.type === 'static' ? '—' : app.open_errors}
                                        </dd>
                                    </div>
                                </dl>

                                <div className="flex items-center justify-between border-t pt-3">
                                    <Meter value={app.uptime_24h} />
                                    <span className="text-muted-foreground text-xs">Revisada {formatRelative(app.last_checked_at)}</span>
                                </div>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
