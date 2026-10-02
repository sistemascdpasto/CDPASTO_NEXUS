import { formatBucket, formatBucketLong, formatPercent } from '@/lib/format';
import { cn } from '@/lib/utils';
import { Area, AreaChart, Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

export interface Series {
    key: string;
    label: string;
    color: string;
}

interface TimeChartProps {
    data: Record<string, unknown>[];
    series: Series[];
    xKey?: string;
    kind?: 'area' | 'bar';
    height?: number;
    format?: (value: number) => string;
    xFormat?: (value: string) => string;
    tooltipXFormat?: (value: string) => string;
    stacked?: boolean;
}

const axisProps = {
    stroke: 'var(--chart-axis)',
    fontSize: 11,
    tickLine: false,
    axisLine: { stroke: 'var(--chart-grid)' },
} as const;

/**
 * Serie temporal de un solo eje Y. Para dos magnitudes distintas, usar dos gráficas.
 */
export function TimeChart({
    data,
    series,
    xKey = 'time',
    kind = 'area',
    height = 220,
    format = (v) => String(v),
    xFormat = formatBucket,
    tooltipXFormat = formatBucketLong,
    stacked = false,
}: TimeChartProps) {
    if (!data.length) {
        return (
            <div className="text-muted-foreground flex items-center justify-center rounded-lg border border-dashed text-sm" style={{ height }}>
                Sin datos en este periodo
            </div>
        );
    }

    const common = {
        data,
        margin: { top: 8, right: 8, bottom: 0, left: 0 },
    };

    const children = (
        <>
            <CartesianGrid vertical={false} stroke="var(--chart-grid)" />
            <XAxis dataKey={xKey} tickFormatter={xFormat} minTickGap={24} {...axisProps} />
            <YAxis tickFormatter={(v) => format(Number(v))} width={56} {...axisProps} axisLine={false} />
            <Tooltip
                cursor={kind === 'area' ? { stroke: 'var(--chart-axis)', strokeDasharray: '3 3' } : { fill: 'var(--chart-grid)', opacity: 0.5 }}
                content={({ active, payload, label }) =>
                    active && payload?.length ? (
                        <div className="bg-popover text-popover-foreground rounded-md border px-3 py-2 text-xs shadow-md">
                            <div className="mb-1 font-medium">{tooltipXFormat(String(label))}</div>
                            {payload.map((item) => {
                                const s = series.find((x) => x.key === item.dataKey);

                                return (
                                    <div key={String(item.dataKey)} className="flex items-center gap-2">
                                        <span className="size-2 rounded-full" style={{ background: s?.color }} />
                                        <span className="text-muted-foreground">{s?.label}</span>
                                        <span className="ml-auto pl-3 font-medium tabular-nums">{format(Number(item.value))}</span>
                                    </div>
                                );
                            })}
                        </div>
                    ) : null
                }
            />
            {series.map((s) =>
                kind === 'area' ? (
                    <Area
                        key={s.key}
                        type="monotone"
                        dataKey={s.key}
                        stroke={s.color}
                        strokeWidth={2}
                        fill={s.color}
                        fillOpacity={0.12}
                        stackId={stacked ? 'a' : undefined}
                        dot={false}
                        activeDot={{ r: 4, strokeWidth: 2, stroke: 'var(--card)' }}
                        isAnimationActive={false}
                        connectNulls
                    />
                ) : (
                    <Bar
                        key={s.key}
                        dataKey={s.key}
                        fill={s.color}
                        radius={[4, 4, 0, 0]}
                        stackId={stacked ? 'a' : undefined}
                        maxBarSize={28}
                        isAnimationActive={false}
                    />
                ),
            )}
        </>
    );

    return (
        <div>
            {series.length > 1 && <Legend series={series} />}
            <ResponsiveContainer width="100%" height={height}>
                {kind === 'area' ? (
                    <AreaChart {...common}>{children}</AreaChart>
                ) : (
                    <BarChart {...common} barCategoryGap={2}>
                        {children}
                    </BarChart>
                )}
            </ResponsiveContainer>
        </div>
    );
}

export function Legend({ series }: { series: Series[] }) {
    return (
        <div className="text-muted-foreground mb-2 flex flex-wrap gap-4 text-xs">
            {series.map((s) => (
                <span key={s.key} className="inline-flex items-center gap-1.5">
                    <span className="h-0.5 w-3 rounded-full" style={{ background: s.color }} />
                    {s.label}
                </span>
            ))}
        </div>
    );
}

function uptimeTone(value: number): { color: string; label: string } {
    if (value >= 99.5) return { color: 'var(--status-good)', label: 'Estable' };
    if (value >= 95) return { color: 'var(--status-warning)', label: 'Interrupciones' };

    return { color: 'var(--status-critical)', label: 'Caídas importantes' };
}

/**
 * Franja de disponibilidad diaria (estilo página de estado). Días sin datos en gris.
 */
export function UptimeStrip({ daily, days = 30 }: { daily: { date: string; uptime: number; checks: number }[]; days?: number }) {
    const byDate = new Map(daily.map((d) => [d.date, d]));
    const today = new Date();
    const cells = Array.from({ length: days }, (_, i) => {
        const date = new Date(today.getFullYear(), today.getMonth(), today.getDate() - (days - 1 - i));
        const key = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;

        return { key, entry: byDate.get(key) };
    });

    return (
        <div>
            <div className="flex h-9 items-stretch gap-[2px]">
                {cells.map(({ key, entry }) => {
                    const tone = entry ? uptimeTone(entry.uptime) : null;

                    return (
                        <div
                            key={key}
                            className={cn('group relative flex-1 rounded-[3px]', !entry && 'bg-muted')}
                            style={tone ? { background: tone.color } : undefined}
                        >
                            <div className="bg-popover text-popover-foreground pointer-events-none absolute bottom-full left-1/2 z-10 mb-2 hidden -translate-x-1/2 rounded-md border px-2 py-1 text-xs whitespace-nowrap shadow-md group-hover:block">
                                <div className="font-medium">{formatBucketLong(key)}</div>
                                {entry ? (
                                    <div>
                                        {formatPercent(entry.uptime)} · {tone?.label} · {entry.checks} verificaciones
                                    </div>
                                ) : (
                                    <div className="text-muted-foreground">Sin verificaciones</div>
                                )}
                            </div>
                        </div>
                    );
                })}
            </div>
            <div className="text-muted-foreground mt-1.5 flex justify-between text-xs">
                <span>Hace {days} días</span>
                <span className="hidden gap-3 sm:flex">
                    <LegendDot color="var(--status-good)" label="≥ 99,5%" />
                    <LegendDot color="var(--status-warning)" label="≥ 95%" />
                    <LegendDot color="var(--status-critical)" label="< 95%" />
                </span>
                <span>Hoy</span>
            </div>
        </div>
    );
}

function LegendDot({ color, label }: { color: string; label: string }) {
    return (
        <span className="inline-flex items-center gap-1">
            <span className="size-2 rounded-[2px]" style={{ background: color }} />
            {label}
        </span>
    );
}

/**
 * Mini barra de proporción (p. ej. uptime) para tablas.
 */
export function Meter({ value, max = 100 }: { value: number | null; max?: number }) {
    if (value === null) return <span className="text-muted-foreground text-xs">—</span>;

    const tone = uptimeTone(value);

    return (
        <div className="flex items-center gap-2">
            <div className="bg-muted h-1.5 w-16 overflow-hidden rounded-full">
                <div className="h-full rounded-full" style={{ width: `${Math.min(100, (value / max) * 100)}%`, background: tone.color }} />
            </div>
            <span className="text-xs tabular-nums">{formatPercent(value)}</span>
        </div>
    );
}
