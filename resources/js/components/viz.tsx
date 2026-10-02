import CountUp from '@/components/reactbits/CountUp';
import { cn } from '@/lib/utils';

/** Color del puntaje de salud: estado reservado, siempre acompañado del número. */
export function scoreColor(score: number | null | undefined): string {
    if (score === null || score === undefined) return 'var(--status-unknown)';
    if (score >= 90) return 'var(--status-good)';
    if (score >= 75) return 'var(--series-1)';
    if (score >= 50) return 'var(--status-warning)';

    return 'var(--status-critical)';
}

export function scoreLabel(score: number | null | undefined): string {
    if (score === null || score === undefined) return 'Sin datos';
    if (score >= 90) return 'Excelente';
    if (score >= 75) return 'Buena';
    if (score >= 50) return 'Regular';

    return 'Crítica';
}

/**
 * Anillo con el puntaje de salud 0-100.
 */
export function HealthRing({
    score,
    size = 56,
    stroke = 6,
    className,
    showLabel = false,
}: {
    score: number | null;
    size?: number;
    stroke?: number;
    className?: string;
    showLabel?: boolean;
}) {
    const radius = (size - stroke) / 2;
    const circumference = 2 * Math.PI * radius;
    const value = score ?? 0;
    const color = scoreColor(score);

    return (
        <div
            className={cn('relative inline-flex shrink-0 flex-col items-center', className)}
            title={`Salud: ${score ?? '—'}/100 · ${scoreLabel(score)}`}
        >
            <svg width={size} height={size} className="-rotate-90">
                <circle cx={size / 2} cy={size / 2} r={radius} fill="none" stroke="var(--muted)" strokeWidth={stroke} />
                <circle
                    cx={size / 2}
                    cy={size / 2}
                    r={radius}
                    fill="none"
                    stroke={color}
                    strokeWidth={stroke}
                    strokeLinecap="round"
                    strokeDasharray={circumference}
                    strokeDashoffset={circumference * (1 - value / 100)}
                    style={{ transition: 'stroke-dashoffset 900ms cubic-bezier(.2,.8,.2,1)' }}
                />
            </svg>
            <span className="absolute inset-0 flex items-center justify-center font-semibold tabular-nums" style={{ fontSize: size * 0.3 }}>
                {score ?? '—'}
            </span>
            {showLabel && <span className="text-muted-foreground mt-1 text-[11px]">{scoreLabel(score)}</span>}
        </div>
    );
}

/**
 * Mini gráfica de línea sin ejes (latencia reciente). Los puntos caídos se marcan en rojo.
 */
export function Sparkline({
    values,
    height = 36,
    className,
    color = 'var(--series-1)',
    failures = [],
}: {
    values: (number | null)[];
    height?: number;
    className?: string;
    color?: string;
    failures?: boolean[];
}) {
    const points = values.map((v) => v ?? 0);
    const width = 100;

    if (points.length < 2) {
        return <div className={cn('bg-muted/50 rounded', className)} style={{ height }} />;
    }

    const max = Math.max(...points, 1);
    const step = width / (points.length - 1);
    const coords = points.map((v, i) => [i * step, height - 2 - (v / max) * (height - 6)] as const);
    const line = coords.map(([x, y], i) => `${i === 0 ? 'M' : 'L'}${x.toFixed(2)},${y.toFixed(2)}`).join(' ');
    const area = `${line} L${width},${height} L0,${height} Z`;
    const gradientId = `spark-${Math.round(max)}-${points.length}`;

    return (
        <svg viewBox={`0 0 ${width} ${height}`} preserveAspectRatio="none" className={cn('w-full', className)} style={{ height }} aria-hidden>
            <defs>
                <linearGradient id={gradientId} x1="0" x2="0" y1="0" y2="1">
                    <stop offset="0%" stopColor={color} stopOpacity={0.28} />
                    <stop offset="100%" stopColor={color} stopOpacity={0} />
                </linearGradient>
            </defs>
            <path d={area} fill={`url(#${gradientId})`} />
            <path d={line} fill="none" stroke={color} strokeWidth={1.5} vectorEffect="non-scaling-stroke" strokeLinejoin="round" />
            {failures.map((failed, i) =>
                failed && coords[i] ? (
                    <circle key={i} cx={coords[i][0]} cy={height - 4} r={1.6} fill="var(--status-critical)" vectorEffect="non-scaling-stroke" />
                ) : null,
            )}
        </svg>
    );
}

/**
 * Número animado (CountUp de React Bits) con formato es-CO.
 */
export function AnimatedNumber({
    value,
    decimals = 0,
    suffix = '',
    className,
}: {
    value: number | null | undefined;
    decimals?: number;
    suffix?: string;
    className?: string;
}) {
    if (value === null || value === undefined) return <span className={className}>—</span>;

    return (
        <span className={cn('tabular-nums', className)}>
            <CountUp to={decimals > 0 ? Number(value.toFixed(decimals)) : value} duration={1.1} separator={decimals > 0 ? '' : '.'} />
            {suffix}
        </span>
    );
}
