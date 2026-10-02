import { EmptyState, NativeSelect, PageHeader, Pagination, Panel, StatCard, Table, applyFilters } from '@/components/nexus-ui';
import { LoginEventBadge } from '@/components/status-badge';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime, formatNumber, formatRelative } from '@/lib/format';
import { type AppRef, type LoginEvent, type Paginated } from '@/types';
import { Head } from '@inertiajs/react';
import { KeyRound, ShieldAlert } from 'lucide-react';
import { useState } from 'react';

interface Filters {
    application_id?: string;
    event?: string;
    search?: string;
    from?: string;
    to?: string;
}

interface Props {
    events: Paginated<LoginEvent>;
    stats: Record<string, number>;
    suspiciousIps: { ip: string; total: number; identifiers: number; last_at: string }[];
    filters: Filters;
    applications: AppRef[];
}

export default function LoginsIndex({ events, stats, suspiciousIps, filters, applications }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const update = (patch: Filters) => applyFilters('/logins', { ...filters, ...patch });

    return (
        <AppLayout breadcrumbs={[{ title: 'Inicios de sesión', href: '/logins' }]}>
            <Head title="Inicios de sesión" />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader title="Historial de inicios de sesión" description="Accesos exitosos y fallidos en todas las aplicaciones." />

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <StatCard label="Ingresos (24 h)" value={formatNumber(stats.login ?? 0)} />
                    <StatCard
                        label="Fallidos (24 h)"
                        value={formatNumber(stats.failed ?? 0)}
                        tone="var(--status-critical)"
                        icon={(stats.failed ?? 0) > 0 ? ShieldAlert : undefined}
                    />
                    <StatCard label="Bloqueos por intentos" value={formatNumber(stats.lockout ?? 0)} />
                    <StatCard label="Salidas (24 h)" value={formatNumber(stats.logout ?? 0)} />
                </div>

                {suspiciousIps.length > 0 && (
                    <Panel
                        title={
                            <span className="inline-flex items-center gap-2">
                                <ShieldAlert className="size-4" style={{ color: 'var(--status-critical)' }} /> IPs con muchos intentos fallidos (24 h)
                            </span>
                        }
                    >
                        <div className="flex flex-wrap gap-2">
                            {suspiciousIps.map((ip) => (
                                <button
                                    key={ip.ip}
                                    onClick={() => update({ search: ip.ip, event: 'failed' })}
                                    className="hover:bg-accent rounded-lg border px-3 py-2 text-left text-sm"
                                >
                                    <div className="font-mono font-medium">{ip.ip}</div>
                                    <div className="text-muted-foreground text-xs">
                                        {ip.total} intentos · {ip.identifiers} cuentas · {formatRelative(ip.last_at)}
                                    </div>
                                </button>
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
                        <option value="">Todas las apps</option>
                        {applications.map((a) => (
                            <option key={a.id} value={a.id}>
                                {a.name}
                            </option>
                        ))}
                    </NativeSelect>
                    <NativeSelect value={filters.event ?? ''} onChange={(e) => update({ event: e.target.value })} aria-label="Tipo">
                        <option value="">Todos los eventos</option>
                        <option value="login">Ingresos</option>
                        <option value="failed">Fallidos</option>
                        <option value="lockout">Bloqueos por intentos</option>
                        <option value="logout">Salidas</option>
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
                        className="min-w-48 flex-1"
                        onSubmit={(e) => {
                            e.preventDefault();
                            update({ search });
                        }}
                    >
                        <Input className="h-9" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Usuario, correo o IP…" />
                    </form>
                </div>

                <Panel>
                    {events.data.length === 0 ? (
                        <EmptyState icon={KeyRound} title="Sin registros" />
                    ) : (
                        <Table className="-m-4">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Evento</th>
                                    <th>Usuario</th>
                                    <th>Aplicación</th>
                                    <th>IP</th>
                                    <th>Dispositivo</th>
                                </tr>
                            </thead>
                            <tbody>
                                {events.data.map((e) => (
                                    <tr key={e.id}>
                                        <td className="text-xs whitespace-nowrap">{formatDateTime(e.occurred_at)}</td>
                                        <td>
                                            <LoginEventBadge event={e.event} />
                                        </td>
                                        <td>
                                            <div>{e.user_name ?? e.identifier ?? '—'}</div>
                                            {e.user_name && e.identifier && <div className="text-muted-foreground text-xs">{e.identifier}</div>}
                                        </td>
                                        <td className="text-xs">{e.application?.name}</td>
                                        <td className="font-mono text-xs">{e.ip}</td>
                                        <td className="text-muted-foreground text-xs" title={e.user_agent ?? undefined}>
                                            {e.device ?? '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </Table>
                    )}
                </Panel>
                <Pagination meta={events} />
            </div>
        </AppLayout>
    );
}
