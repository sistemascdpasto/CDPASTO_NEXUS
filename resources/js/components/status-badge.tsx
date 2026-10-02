import { cn } from '@/lib/utils';
import { type AppStatus } from '@/types';
import { AlertTriangle, CheckCircle2, CircleHelp, Info, OctagonAlert, XCircle } from 'lucide-react';

const statusMeta: Record<AppStatus, { label: string; color: string; Icon: typeof CheckCircle2 }> = {
    online: { label: 'En línea', color: 'var(--status-good)', Icon: CheckCircle2 },
    degraded: { label: 'Degradada', color: 'var(--status-warning)', Icon: AlertTriangle },
    down: { label: 'Caída', color: 'var(--status-critical)', Icon: XCircle },
    unknown: { label: 'Sin datos', color: 'var(--status-unknown)', Icon: CircleHelp },
};

export function statusLabel(status: AppStatus): string {
    return statusMeta[status]?.label ?? status;
}

export function statusColor(status: AppStatus): string {
    return statusMeta[status]?.color ?? 'var(--status-unknown)';
}

/** Estado de una app: el color siempre va con icono + texto. */
export function StatusBadge({ status, className }: { status: AppStatus; className?: string }) {
    const { label, color, Icon } = statusMeta[status] ?? statusMeta.unknown;

    return (
        <span className={cn('inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-xs font-medium', className)}>
            <Icon className="size-3.5" style={{ color }} aria-hidden />
            {label}
        </span>
    );
}

export function StatusDot({ status }: { status: AppStatus }) {
    return (
        <span className="relative flex size-2.5" title={statusLabel(status)}>
            {status === 'down' && (
                <span className="absolute inline-flex size-full animate-ping rounded-full opacity-60" style={{ background: statusColor(status) }} />
            )}
            <span className="relative inline-flex size-2.5 rounded-full" style={{ background: statusColor(status) }} />
        </span>
    );
}

const severityMeta = {
    critical: { label: 'Crítica', color: 'var(--status-critical)', Icon: OctagonAlert },
    warning: { label: 'Advertencia', color: 'var(--status-warning)', Icon: AlertTriangle },
    info: { label: 'Info', color: 'var(--status-good)', Icon: Info },
} as const;

export function SeverityIcon({ severity, className }: { severity: keyof typeof severityMeta; className?: string }) {
    const { color, Icon, label } = severityMeta[severity] ?? severityMeta.info;

    return <Icon className={cn('size-4 shrink-0', className)} style={{ color }} aria-label={label} />;
}

export function SeverityBadge({ severity }: { severity: keyof typeof severityMeta }) {
    const { label } = severityMeta[severity] ?? severityMeta.info;

    return (
        <span className="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-xs font-medium">
            <SeverityIcon severity={severity} className="size-3.5" />
            {label}
        </span>
    );
}

const loginMeta: Record<string, { label: string; color: string }> = {
    login: { label: 'Ingreso', color: 'var(--status-good)' },
    failed: { label: 'Fallido', color: 'var(--status-critical)' },
    logout: { label: 'Salida', color: 'var(--status-unknown)' },
    lockout: { label: 'Bloqueo por intentos', color: 'var(--status-serious)' },
    blocked: { label: 'Bloqueado', color: 'var(--status-critical)' },
};

export function LoginEventBadge({ event }: { event: string }) {
    const meta = loginMeta[event] ?? { label: event, color: 'var(--status-unknown)' };

    return (
        <span className="inline-flex items-center gap-1.5 text-xs font-medium">
            <span className="size-2 rounded-full" style={{ background: meta.color }} aria-hidden />
            {meta.label}
        </span>
    );
}

const actionLabels: Record<string, string> = {
    created: 'Creó',
    updated: 'Editó',
    deleted: 'Eliminó',
    restored: 'Restauró',
    uploaded: 'Subió archivo',
    downloaded: 'Descargó',
    exported: 'Exportó',
    viewed: 'Consultó',
};

const actionColors: Record<string, string> = {
    created: 'var(--status-good)',
    updated: 'var(--series-1)',
    deleted: 'var(--status-critical)',
    uploaded: 'var(--series-2)',
    downloaded: 'var(--status-warning)',
    exported: 'var(--status-warning)',
};

export function actionLabel(action: string): string {
    return actionLabels[action] ?? action;
}

export function ActionBadge({ action }: { action: string }) {
    return (
        <span className="inline-flex items-center gap-1.5 text-xs font-medium whitespace-nowrap">
            <span className="size-2 rounded-full" style={{ background: actionColors[action] ?? 'var(--status-unknown)' }} aria-hidden />
            {actionLabel(action)}
        </span>
    );
}
