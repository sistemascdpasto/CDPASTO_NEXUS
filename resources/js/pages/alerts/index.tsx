import { EmptyState, NativeSelect, PageHeader, Pagination, Panel, applyFilters } from '@/components/nexus-ui';
import { SeverityIcon } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime, formatRelative } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type Alert, type AppRef, type Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Bell, Check, CheckCheck, Mail, MessageCircle } from 'lucide-react';

interface Props {
    alerts: Paginated<Alert>;
    filters: { application_id?: string; type?: string; status?: string };
    applications: AppRef[];
    types: { value: string; label: string }[];
    channels: { mail: boolean; whatsapp: boolean };
}

export default function AlertsIndex({ alerts, filters, applications, types, channels }: Props) {
    const update = (patch: Props['filters']) => applyFilters('/alerts', { ...filters, ...patch });
    const typeLabel = (value: string) => types.find((t) => t.value === value)?.label ?? value;

    return (
        <AppLayout breadcrumbs={[{ title: 'Alertas', href: '/alerts' }]}>
            <Head title="Alertas" />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title="Alertas"
                    description={
                        <span className="flex flex-wrap items-center gap-3">
                            <span className="inline-flex items-center gap-1">
                                <Mail className="size-3.5" /> Correo {channels.mail ? 'activo' : 'sin configurar (MAIL_MAILER=log)'}
                            </span>
                            <span className="inline-flex items-center gap-1">
                                <MessageCircle className="size-3.5" /> WhatsApp {channels.whatsapp ? 'activo' : 'sin configurar'}
                            </span>
                        </span>
                    }
                    actions={
                        <Button variant="outline" size="sm" onClick={() => router.post('/alerts/acknowledge-all', {}, { preserveScroll: true })}>
                            <CheckCheck /> Marcar todas como atendidas
                        </Button>
                    }
                />

                <div className="flex flex-wrap gap-2">
                    <NativeSelect value={filters.status ?? ''} onChange={(e) => update({ status: e.target.value })} aria-label="Estado">
                        <option value="">Todas</option>
                        <option value="open">Sin atender</option>
                    </NativeSelect>
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
                    <NativeSelect value={filters.type ?? ''} onChange={(e) => update({ type: e.target.value })} aria-label="Tipo">
                        <option value="">Todos los tipos</option>
                        {types.map((t) => (
                            <option key={t.value} value={t.value}>
                                {t.label}
                            </option>
                        ))}
                    </NativeSelect>
                </div>

                <Panel>
                    {alerts.data.length === 0 ? (
                        <EmptyState icon={Bell} title="Sin alertas" />
                    ) : (
                        <ul className="-m-4 divide-y">
                            {alerts.data.map((a) => (
                                <li key={a.id} className={cn('flex gap-3 px-4 py-3', a.acknowledged_at && 'opacity-60')}>
                                    <SeverityIcon severity={a.severity} className="mt-0.5" />
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-baseline gap-x-2">
                                            <span className="font-medium">{a.title}</span>
                                            <span className="text-muted-foreground text-xs">{typeLabel(a.type)}</span>
                                        </div>
                                        <p className="text-sm">{a.message}</p>
                                        <p className="text-muted-foreground mt-0.5 text-xs">
                                            {formatDateTime(a.created_at)} ({formatRelative(a.created_at)})
                                            {a.application && (
                                                <>
                                                    {' · '}
                                                    <Link href={`/applications/${a.application.id}`} className="hover:underline">
                                                        {a.application.name}
                                                    </Link>
                                                </>
                                            )}
                                            {a.acknowledged_at && ` · Atendida por ${a.acknowledged_by?.name ?? '—'}`}
                                        </p>
                                    </div>
                                    {!a.acknowledged_at && a.severity !== 'info' && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => router.post(`/alerts/${a.id}/acknowledge`, {}, { preserveScroll: true })}
                                        >
                                            <Check /> Atendida
                                        </Button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>
                <Pagination meta={alerts} />
            </div>
        </AppLayout>
    );
}
