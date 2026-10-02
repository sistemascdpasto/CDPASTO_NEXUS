import { EmptyState, NativeSelect, PageHeader, Pagination, Panel, StatCard, applyFilters } from '@/components/nexus-ui';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime, formatRelative } from '@/lib/format';
import { type AppRef, type Paginated } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { CheckCircle2, Clock, NotebookPen, ShieldCheck, Siren, Timer } from 'lucide-react';
import { useState } from 'react';

interface Incident {
    id: number;
    application_id: number;
    application: AppRef;
    started_at: string;
    resolved_at: string | null;
    current_duration: number;
    cause: string | null;
    failed_checks: number;
    notes: string | null;
    notes_author: AppRef | null;
    can_annotate: boolean;
}

interface Props {
    incidents: Paginated<Incident>;
    stats: { application_id: number; name: string; count: number; downtime_seconds: number; mttr_seconds: number | null; last_at: string }[];
    summary: { open: number; count_30d: number; downtime_30d: number; mttr_30d: number | null };
    filters: { application_id?: string; status?: string };
    applications: AppRef[];
}

export function formatDuration(seconds: number | null | undefined): string {
    if (seconds === null || seconds === undefined) return '—';
    if (seconds < 60) return `${seconds} s`;
    const minutes = Math.round(seconds / 60);
    if (minutes < 60) return `${minutes} min`;
    const hours = Math.floor(minutes / 60);

    return `${hours} h ${minutes % 60} min`;
}

export default function IncidentsIndex({ incidents, stats, summary, filters, applications }: Props) {
    const update = (patch: Props['filters']) => applyFilters('/incidents', { ...filters, ...patch });

    return (
        <AppLayout breadcrumbs={[{ title: 'Incidentes', href: '/incidents' }]}>
            <Head title="Incidentes" />
            <div className="flex flex-col gap-4 p-4 sm:p-6">
                <PageHeader
                    title="Incidentes"
                    description="Cada caída confirmada abre un incidente; la recuperación lo cierra. Documenta la causa raíz para no repetirla."
                />

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <StatCard
                        label="Abiertos ahora"
                        value={summary.open}
                        icon={Siren}
                        tone={summary.open > 0 ? 'var(--status-critical)' : 'var(--status-good)'}
                        hint={summary.open > 0 ? 'Hay sistemas caídos' : 'Ningún sistema caído'}
                    />
                    <StatCard label="Incidentes (30 días)" value={summary.count_30d} icon={ShieldCheck} />
                    <StatCard label="Tiempo caído (30 días)" value={formatDuration(summary.downtime_30d)} icon={Clock} />
                    <StatCard label="MTTR (30 días)" value={formatDuration(summary.mttr_30d)} hint="Tiempo medio de recuperación" icon={Timer} />
                </div>

                {stats.length > 0 && (
                    <Panel title="Por sistema" description="Últimos 30 días">
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {stats.map((s) => (
                                <div key={s.application_id} className="rounded-lg border p-3">
                                    <Link href={`/applications/${s.application_id}`} className="font-medium hover:underline">
                                        {s.name}
                                    </Link>
                                    <div className="text-muted-foreground mt-1 grid grid-cols-3 gap-2 text-xs">
                                        <span>
                                            <b className="text-foreground block text-base tabular-nums">{s.count}</b>caídas
                                        </span>
                                        <span>
                                            <b className="text-foreground block text-base">{formatDuration(s.downtime_seconds)}</b>caído
                                        </span>
                                        <span>
                                            <b className="text-foreground block text-base">{formatDuration(s.mttr_seconds)}</b>MTTR
                                        </span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </Panel>
                )}

                <div className="flex flex-wrap gap-2">
                    <NativeSelect
                        value={filters.application_id ?? ''}
                        onChange={(e) => update({ application_id: e.target.value })}
                        aria-label="Aplicación"
                    >
                        <option value="">Todos los sistemas</option>
                        {applications.map((a) => (
                            <option key={a.id} value={a.id}>
                                {a.name}
                            </option>
                        ))}
                    </NativeSelect>
                    <NativeSelect value={filters.status ?? ''} onChange={(e) => update({ status: e.target.value })} aria-label="Estado">
                        <option value="">Todos</option>
                        <option value="open">Abiertos</option>
                        <option value="resolved">Resueltos</option>
                    </NativeSelect>
                </div>

                <Panel>
                    {incidents.data.length === 0 ? (
                        <EmptyState icon={CheckCircle2} title="Sin incidentes registrados">
                            Cuando un sistema deje de responder en dos verificaciones seguidas, aparecerá aquí.
                        </EmptyState>
                    ) : (
                        <ol className="relative -my-2 space-y-0 border-l pl-6">
                            {incidents.data.map((incident) => (
                                <IncidentRow key={incident.id} incident={incident} />
                            ))}
                        </ol>
                    )}
                </Panel>
                <Pagination meta={incidents} />
            </div>
        </AppLayout>
    );
}

function IncidentRow({ incident }: { incident: Incident }) {
    const [editing, setEditing] = useState(false);
    const form = useForm({ notes: incident.notes ?? '' });
    const open = incident.resolved_at === null;
    const color = open ? 'var(--status-critical)' : 'var(--status-good)';

    return (
        <li className="relative py-4">
            <span
                className="bg-card absolute top-5 -left-[31px] flex size-4 items-center justify-center rounded-full border-2"
                style={{ borderColor: color }}
            >
                <span className="size-1.5 rounded-full" style={{ background: color }} />
            </span>
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <p className="font-medium">
                        <Link href={`/applications/${incident.application_id}`} className="hover:underline">
                            {incident.application.name}
                        </Link>{' '}
                        {open ? (
                            <span className="text-[var(--status-critical)]">está caída</span>
                        ) : (
                            <span className="text-muted-foreground">estuvo caída {formatDuration(incident.current_duration)}</span>
                        )}
                    </p>
                    <p className="text-muted-foreground text-xs">
                        {formatDateTime(incident.started_at)}
                        {incident.resolved_at && ` → ${formatDateTime(incident.resolved_at)}`} · {incident.failed_checks} verificaciones fallidas
                        {open && ` · lleva ${formatDuration(incident.current_duration)}`}
                    </p>
                    {incident.cause && <p className="bg-muted/60 mt-2 inline-block rounded px-2 py-1 font-mono text-xs">{incident.cause}</p>}
                </div>
                {incident.can_annotate && !editing && (
                    <Button variant="ghost" size="sm" onClick={() => setEditing(true)}>
                        <NotebookPen /> {incident.notes ? 'Editar causa raíz' : 'Documentar causa raíz'}
                    </Button>
                )}
            </div>

            {incident.notes && !editing && (
                <div className="mt-3 rounded-lg border-l-4 bg-[var(--accent)] p-3 text-sm" style={{ borderColor: 'var(--primary)' }}>
                    <p className="whitespace-pre-wrap">{incident.notes}</p>
                    <p className="text-muted-foreground mt-1 text-xs">— {incident.notes_author?.name ?? 'Nexus'}</p>
                </div>
            )}

            {editing && (
                <form
                    className="mt-3 space-y-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.patch(`/incidents/${incident.id}`, { preserveScroll: true, onSuccess: () => setEditing(false) });
                    }}
                >
                    <Textarea
                        value={form.data.notes}
                        onChange={(e) => form.setData('notes', e.target.value)}
                        rows={4}
                        placeholder="¿Qué pasó? ¿Cómo se resolvió? ¿Qué haremos para que no se repita?"
                    />
                    <div className="flex gap-2">
                        <Button type="submit" size="sm" disabled={form.processing}>
                            Guardar
                        </Button>
                        <Button type="button" size="sm" variant="ghost" onClick={() => setEditing(false)}>
                            Cancelar
                        </Button>
                    </div>
                </form>
            )}
            <span className="sr-only">{formatRelative(incident.started_at)}</span>
        </li>
    );
}
