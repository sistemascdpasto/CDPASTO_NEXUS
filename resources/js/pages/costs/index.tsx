import { TimeChart } from '@/components/charts';
import { EmptyState, PageHeader, Panel, StatCard, Table } from '@/components/nexus-ui';
import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { CalendarClock, CircleDollarSign, Cpu, Database, HardDrive, Info, TrendingUp } from 'lucide-react';

interface Service {
    application_id: number;
    name: string | null;
    kind: 'app' | 'database';
    service: string | null;
    avg_cpu: number;
    avg_memory_gb: number;
    egress_gb: number;
    cpu_cost: number;
    memory_cost: number;
    egress_cost: number;
    projected: number;
    to_date: number;
}

interface Props {
    services: Service[];
    total_projected: number;
    total_to_date: number;
    month_progress: number;
    daily: { date: string; cost: number }[];
    prices: { vcpu_month: number; memory_gb_month: number; egress_gb: number; usd_to_cop: number };
    month: string;
}

const usd = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'USD', minimumFractionDigits: 2 });
const cop = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });

export default function CostsIndex({ services, total_projected, total_to_date, month_progress, daily, prices, month }: Props) {
    const max = Math.max(...services.map((s) => s.projected), 0.01);
    const byApp = Object.values(
        services.reduce<Record<number, { name: string; total: number }>>((acc, s) => {
            acc[s.application_id] ??= { name: s.name ?? '—', total: 0 };
            acc[s.application_id].total += s.projected;

            return acc;
        }, {}),
    ).sort((a, b) => b.total - a.total);

    return (
        <AppLayout breadcrumbs={[{ title: 'Costos de Railway', href: '/costs' }]}>
            <Head title="Costos de Railway" />
            <div className="flex flex-col gap-4 p-4 sm:p-6">
                <PageHeader
                    title="Costos de Railway"
                    description={`${month.charAt(0).toUpperCase()}${month.slice(1)} · estimado a partir del consumo real de CPU, memoria y red de cada servicio`}
                />

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <StatCard
                        label="Proyección del mes"
                        value={usd.format(total_projected)}
                        hint={`≈ ${cop.format(total_projected * prices.usd_to_cop)}`}
                        icon={TrendingUp}
                    />
                    <StatCard
                        label="Consumido a la fecha"
                        value={usd.format(total_to_date)}
                        hint={`≈ ${cop.format(total_to_date * prices.usd_to_cop)}`}
                        icon={CircleDollarSign}
                    />
                    <StatCard label="Avance del mes" value={`${Math.round(month_progress * 100)}%`} icon={CalendarClock} />
                    <StatCard
                        label="Servicios medidos"
                        value={services.length}
                        hint={`${services.filter((s) => s.kind === 'database').length} bases de datos`}
                        icon={Database}
                    />
                </div>

                {services.length === 0 ? (
                    <Panel>
                        <EmptyState icon={CircleDollarSign} title="Aún no hay métricas de Railway este mes">
                            Se acumulan cada 5 minutos desde que se configuró el token de Railway.
                        </EmptyState>
                    </Panel>
                ) : (
                    <>
                        <div className="grid gap-4 lg:grid-cols-[1.4fr_1fr]">
                            <Panel title="Costo diario estimado" description="USD por día según el consumo de ese día">
                                <TimeChart
                                    data={daily}
                                    xKey="date"
                                    kind="bar"
                                    series={[{ key: 'cost', label: 'Costo', color: 'var(--series-1)' }]}
                                    format={(v) => usd.format(v)}
                                    height={220}
                                />
                            </Panel>
                            <Panel title="Por sistema" description="App + su base de datos, proyección mensual">
                                <ul className="space-y-3">
                                    {byApp.map((row) => (
                                        <li key={row.name}>
                                            <div className="flex justify-between text-sm">
                                                <span className="truncate font-medium">{row.name}</span>
                                                <span className="tabular-nums">{usd.format(row.total)}</span>
                                            </div>
                                            <div className="bg-muted mt-1.5 h-2 overflow-hidden rounded-full">
                                                <div
                                                    className="h-full rounded-full"
                                                    style={{
                                                        width: `${(row.total / Math.max(...byApp.map((r) => r.total), 0.01)) * 100}%`,
                                                        background: 'var(--series-1)',
                                                    }}
                                                />
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            </Panel>
                        </div>

                        <Panel title="Detalle por servicio">
                            <Table className="-m-4">
                                <thead>
                                    <tr>
                                        <th>Servicio</th>
                                        <th className="text-right">CPU prom.</th>
                                        <th className="text-right">RAM prom.</th>
                                        <th className="text-right">Egress</th>
                                        <th className="text-right">CPU</th>
                                        <th className="text-right">RAM</th>
                                        <th className="text-right">Red</th>
                                        <th className="w-48">Proyección mensual</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {services.map((s) => (
                                        <tr key={`${s.application_id}-${s.kind}`}>
                                            <td>
                                                <div className="flex items-center gap-2">
                                                    {s.kind === 'database' ? (
                                                        <Database className="text-muted-foreground size-4" />
                                                    ) : (
                                                        <Cpu className="text-muted-foreground size-4" />
                                                    )}
                                                    <div>
                                                        <div className="font-medium">{s.service ?? s.name}</div>
                                                        <div className="text-muted-foreground text-xs">
                                                            {s.name} · {s.kind === 'database' ? 'base de datos' : 'aplicación'}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="text-right font-mono text-xs tabular-nums">{s.avg_cpu.toFixed(3)} vCPU</td>
                                            <td className="text-right font-mono text-xs tabular-nums">
                                                {s.avg_memory_gb >= 1
                                                    ? `${s.avg_memory_gb.toFixed(2)} GB`
                                                    : `${Math.round(s.avg_memory_gb * 1024)} MB`}
                                            </td>
                                            <td className="text-right font-mono text-xs tabular-nums">{s.egress_gb.toFixed(2)} GB</td>
                                            <td className="text-right text-xs tabular-nums">{usd.format(s.cpu_cost)}</td>
                                            <td className="text-right text-xs tabular-nums">{usd.format(s.memory_cost)}</td>
                                            <td className="text-right text-xs tabular-nums">{usd.format(s.egress_cost)}</td>
                                            <td>
                                                <div className="flex items-center gap-2">
                                                    <div className="bg-muted h-1.5 flex-1 overflow-hidden rounded-full">
                                                        <div
                                                            className="h-full rounded-full"
                                                            style={{ width: `${(s.projected / max) * 100}%`, background: 'var(--series-1)' }}
                                                        />
                                                    </div>
                                                    <span className="w-16 text-right text-sm font-semibold tabular-nums">
                                                        {usd.format(s.projected)}
                                                    </span>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </Table>
                        </Panel>
                    </>
                )}

                <p className="text-muted-foreground flex items-start gap-2 text-xs">
                    <Info className="mt-0.5 size-3.5 shrink-0" />
                    <span>
                        Estimación con las tarifas de Railway: {usd.format(prices.vcpu_month)} por vCPU/mes, {usd.format(prices.memory_gb_month)} por
                        GB de RAM/mes y {usd.format(prices.egress_gb)} por GB de salida. No incluye volúmenes, créditos ni el plan base; la factura
                        oficial está en Railway. <HardDrive className="inline size-3" /> Tasa de cambio de referencia: {cop.format(prices.usd_to_cop)}{' '}
                        por dólar.
                    </span>
                </p>
            </div>
        </AppLayout>
    );
}
