import { EmptyState, Panel, StatCard } from '@/components/nexus-ui';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { formatDateTime, formatRelative } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type Deployment } from '@/types';
import { router } from '@inertiajs/react';
import {
    CheckCircle2,
    Construction,
    Eraser,
    HardDrive,
    ListRestart,
    LoaderCircle,
    Power,
    RefreshCw,
    Rocket,
    RotateCcw,
    ShieldAlert,
    ShieldCheck,
    XCircle,
} from 'lucide-react';
import { type ReactNode, useEffect, useState } from 'react';

export interface SecurityCheck {
    key: string;
    label: string;
    ok: boolean;
    detail: string;
}

export interface AgentInfoData {
    environment: {
        app_name: string;
        env: string;
        debug: boolean;
        url: string;
        timezone: string;
        php: string;
        laravel: string;
        agent: string;
        drivers: Record<string, string>;
        config_cached: boolean;
        routes_cached: boolean;
        maintenance: boolean;
    };
    packages: Record<string, string>;
    security: SecurityCheck[];
    storage: { total_gb: number; used_gb: number; used_percent: number } | null;
    failed_jobs: { total: number; recent: { id: number; queue: string; job: string | null; exception: string; failed_at: string }[] };
    users: { total: number; active?: number; created_last_30d: number } | null;
}

interface OperationsProps {
    application: {
        id: number;
        name: string;
        type: 'laravel' | 'static';
        railway_service_id: string | null;
        info: AgentInfoData | null;
        info_at: string | null;
        agent_version: string | null;
    };
}

type Action = {
    key: string;
    title: string;
    description: string;
    confirm: string;
    destructive?: boolean;
    url: string;
    data?: Record<string, string>;
};

/**
 * Pestaña de operaciones de superusuario: Railway, mantenimiento, caché, cola, seguridad y entorno.
 */
export function OperationsTab({ application: app }: OperationsProps) {
    const [pending, setPending] = useState<Action | null>(null);
    const info = app.info;
    const maintenance = info?.environment.maintenance ?? false;
    const hasAgent = app.type === 'laravel' && Boolean(app.agent_version);

    const actions: Action[] = [
        ...(app.railway_service_id
            ? [
                  {
                      key: 'restart',
                      title: 'Reiniciar servicio',
                      description:
                          'Reinicia el contenedor en Railway sin reconstruir. Útil si la app quedó colgada. Los usuarios pierden unos segundos de servicio.',
                      confirm: 'Reiniciar',
                      destructive: true,
                      url: `/applications/${app.id}/railway/restart`,
                  },
                  {
                      key: 'redeploy',
                      title: 'Redesplegar',
                      description: 'Railway vuelve a construir y publicar el último commit. Tarda algunos minutos.',
                      confirm: 'Redesplegar',
                      destructive: true,
                      url: `/applications/${app.id}/railway/redeploy`,
                  },
              ]
            : []),
        ...(hasAgent
            ? [
                  maintenance
                      ? {
                            key: 'maintenance_off',
                            title: 'Quitar modo mantenimiento',
                            description: 'La aplicación vuelve a estar disponible para los usuarios.',
                            confirm: 'Reactivar',
                            url: `/applications/${app.id}/operations`,
                            data: { type: 'maintenance_off' },
                        }
                      : {
                            key: 'maintenance_on',
                            title: 'Activar modo mantenimiento',
                            description:
                                'Los usuarios verán la página de mantenimiento hasta que la reactives. Nexus sigue pudiendo comunicarse con la app.',
                            confirm: 'Poner en mantenimiento',
                            destructive: true,
                            url: `/applications/${app.id}/operations`,
                            data: { type: 'maintenance_on' },
                        },
                  {
                      key: 'cache_clear',
                      title: 'Limpiar caché',
                      description: 'Ejecuta cache:clear en la app. Úsalo si ves datos desactualizados.',
                      confirm: 'Limpiar',
                      url: `/applications/${app.id}/operations`,
                      data: { type: 'cache_clear' },
                  },
                  {
                      key: 'queue_retry',
                      title: 'Reintentar trabajos fallidos',
                      description: 'Envía de nuevo a la cola todos los trabajos fallidos (correos, notificaciones, importaciones…).',
                      confirm: 'Reintentar',
                      url: `/applications/${app.id}/operations`,
                      data: { type: 'queue_retry' },
                  },
              ]
            : []),
    ];

    const icons: Record<string, ReactNode> = {
        restart: <Power className="size-4" />,
        redeploy: <Rocket className="size-4" />,
        maintenance_on: <Construction className="size-4" />,
        maintenance_off: <CheckCircle2 className="size-4" />,
        cache_clear: <Eraser className="size-4" />,
        queue_retry: <ListRestart className="size-4" />,
    };

    return (
        <div className="flex flex-col gap-4">
            {maintenance && (
                <div className="flex items-center gap-2 rounded-lg border-2 p-3 text-sm font-medium" style={{ borderColor: 'var(--status-warning)' }}>
                    <Construction className="size-4" style={{ color: 'var(--status-warning)' }} />
                    La aplicación está en modo mantenimiento: los usuarios no pueden usarla.
                </div>
            )}

            <Panel title="Acciones" description="Cada acción queda registrada en la auditoría del panel">
                {actions.length === 0 ? (
                    <EmptyState title="Sin acciones disponibles">
                        Vincula un servicio de Railway o instala el agente Nexus para habilitar operaciones.
                    </EmptyState>
                ) : (
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {actions.map((action) => (
                            <button
                                key={action.key}
                                onClick={() => setPending(action)}
                                className="hover:bg-accent/50 flex flex-col items-start gap-1 rounded-lg border p-3 text-left"
                            >
                                <span className="flex items-center gap-2 text-sm font-medium">
                                    {icons[action.key]}
                                    {action.title}
                                </span>
                                <span className="text-muted-foreground text-xs">{action.description}</span>
                            </button>
                        ))}
                    </div>
                )}
            </Panel>

            {app.type === 'laravel' && (
                <>
                    <div className="flex items-center justify-between gap-2">
                        <p className="text-muted-foreground text-xs">
                            {app.info_at ? `Ficha técnica actualizada ${formatRelative(app.info_at)}` : 'Aún no hay ficha técnica del agente.'}
                        </p>
                        <Button variant="outline" size="sm" onClick={() => router.post(`/applications/${app.id}/info`, {}, { preserveScroll: true })}>
                            <RefreshCw /> Actualizar ficha
                        </Button>
                    </div>

                    {info && <InfoPanels info={info} />}
                </>
            )}

            {pending && <ConfirmAction action={pending} onClose={() => setPending(null)} />}
        </div>
    );
}

function InfoPanels({ info }: { info: AgentInfoData }) {
    const failing = info.security.filter((c) => !c.ok);

    return (
        <>
            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <StatCard
                    label="Seguridad"
                    value={failing.length === 0 ? 'Sin hallazgos' : `${failing.length} hallazgos`}
                    icon={failing.length === 0 ? ShieldCheck : ShieldAlert}
                    tone={failing.length === 0 ? 'var(--status-good)' : 'var(--status-critical)'}
                />
                <StatCard
                    label="Trabajos fallidos"
                    value={info.failed_jobs.total}
                    icon={ListRestart}
                    tone={info.failed_jobs.total > 0 ? 'var(--status-serious)' : undefined}
                />
                <StatCard
                    label="Almacenamiento"
                    value={info.storage ? `${info.storage.used_percent}%` : '—'}
                    hint={info.storage ? `${info.storage.used_gb} de ${info.storage.total_gb} GB` : 'No disponible'}
                    icon={HardDrive}
                    tone={info.storage && info.storage.used_percent > 85 ? 'var(--status-critical)' : undefined}
                />
                <StatCard
                    label="Usuarios registrados"
                    value={info.users?.total ?? '—'}
                    hint={info.users ? `${info.users.active ?? '—'} activos · ${info.users.created_last_30d} nuevos en 30 días` : undefined}
                />
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                <Panel title="Chequeos de seguridad">
                    <ul className="space-y-2">
                        {info.security.map((check) => (
                            <li key={check.key} className="flex gap-2 text-sm">
                                {check.ok ? (
                                    <CheckCircle2 className="mt-0.5 size-4 shrink-0" style={{ color: 'var(--status-good)' }} />
                                ) : (
                                    <XCircle className="mt-0.5 size-4 shrink-0" style={{ color: 'var(--status-critical)' }} />
                                )}
                                <div>
                                    <p className={cn(!check.ok && 'font-medium')}>{check.label}</p>
                                    {!check.ok && <p className="text-muted-foreground text-xs">{check.detail}</p>}
                                </div>
                            </li>
                        ))}
                    </ul>
                </Panel>

                <Panel title="Entorno">
                    <dl className="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                        <Item label="Entorno">{info.environment.env}</Item>
                        <Item label="Depuración">{info.environment.debug ? 'Activada' : 'Desactivada'}</Item>
                        <Item label="PHP">{info.environment.php}</Item>
                        <Item label="Laravel">{info.environment.laravel}</Item>
                        <Item label="Zona horaria">{info.environment.timezone}</Item>
                        <Item label="Agente Nexus">{info.environment.agent}</Item>
                        {Object.entries(info.environment.drivers).map(([key, value]) => (
                            <Item key={key} label={`Driver ${key}`}>
                                {value}
                            </Item>
                        ))}
                        <Item label="Config / rutas en caché">
                            {info.environment.config_cached ? 'Sí' : 'No'} / {info.environment.routes_cached ? 'Sí' : 'No'}
                        </Item>
                    </dl>
                    {Object.keys(info.packages).length > 0 && (
                        <div className="mt-4 border-t pt-3">
                            <p className="text-muted-foreground mb-1 text-xs">Paquetes</p>
                            <ul className="grid gap-1 font-mono text-xs sm:grid-cols-2">
                                {Object.entries(info.packages).map(([name, version]) => (
                                    <li key={name} className="flex justify-between gap-2">
                                        <span className="truncate">{name}</span>
                                        <span className="text-muted-foreground">{version}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </Panel>
            </div>

            {info.failed_jobs.recent.length > 0 && (
                <Panel title="Últimos trabajos fallidos">
                    <ul className="-my-1 divide-y text-sm">
                        {info.failed_jobs.recent.map((job) => (
                            <li key={job.id} className="py-2">
                                <div className="flex justify-between gap-2">
                                    <span className="font-medium">{job.job ?? 'Trabajo'}</span>
                                    <span className="text-muted-foreground shrink-0 text-xs">
                                        {job.queue} · {formatDateTime(job.failed_at)}
                                    </span>
                                </div>
                                <p className="text-muted-foreground truncate font-mono text-xs" title={job.exception}>
                                    {job.exception}
                                </p>
                            </li>
                        ))}
                    </ul>
                </Panel>
            )}
        </>
    );
}

function Item({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div>
            <dt className="text-muted-foreground text-xs">{label}</dt>
            <dd>{children}</dd>
        </div>
    );
}

function ConfirmAction({ action, onClose }: { action: Action; onClose: () => void }) {
    const [reason, setReason] = useState('');
    const [processing, setProcessing] = useState(false);

    const submit = () =>
        router.post(
            action.url,
            { ...action.data, reason },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => {
                    setProcessing(false);
                    onClose();
                },
            },
        );

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogTitle>{action.title}</DialogTitle>
                <DialogDescription>{action.description}</DialogDescription>
                <Input value={reason} onChange={(e) => setReason(e.target.value)} placeholder="Motivo (queda en la auditoría del panel)" />
                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancelar</Button>
                    </DialogClose>
                    <Button variant={action.destructive ? 'destructive' : 'default'} onClick={submit} disabled={processing}>
                        {processing && <LoaderCircle className="animate-spin" />}
                        {action.confirm}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/**
 * Visor de logs de un despliegue de Railway.
 */
export function DeploymentLogsDialog({
    application,
    deployment,
    onClose,
}: {
    application: { id: number };
    deployment: Deployment;
    onClose: () => void;
}) {
    const [logs, setLogs] = useState<{ message: string; severity: string; timestamp: string }[] | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [filter, setFilter] = useState('');
    const [reload, setReload] = useState(0);

    useEffect(() => {
        setLogs(null);
        setError(null);

        fetch(`/applications/${application.id}/deployments/${deployment.id}/logs`, { headers: { Accept: 'application/json' } })
            .then(async (response) => {
                const body = await response.json();

                if (!response.ok) throw new Error(body.message ?? `HTTP ${response.status}`);

                setLogs(body.logs);
            })
            .catch((e: Error) => setError(e.message));
    }, [application.id, deployment.id, reload]);

    const visible = (logs ?? []).filter((line) => line.message.toLowerCase().includes(filter.toLowerCase()));

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-w-4xl">
                <DialogTitle>Logs del despliegue</DialogTitle>
                <DialogDescription>
                    {deployment.commit_message ?? 'Despliegue'} · {formatDateTime(deployment.deployed_at)}
                </DialogDescription>
                <div className="flex gap-2">
                    <Input
                        value={filter}
                        onChange={(e) => setFilter(e.target.value)}
                        placeholder="Filtrar líneas (ej. error, SQLSTATE)…"
                        className="h-8"
                    />
                    <Button variant="outline" size="sm" className="h-8" onClick={() => setReload((n) => n + 1)}>
                        <RotateCcw /> Recargar
                    </Button>
                </div>
                <div className="bg-muted h-[60vh] overflow-auto rounded-md p-3 font-mono text-[11px] leading-relaxed">
                    {error && <p className="text-destructive">{error}</p>}
                    {!logs && !error && (
                        <p className="text-muted-foreground inline-flex items-center gap-2">
                            <LoaderCircle className="size-3 animate-spin" /> Cargando logs desde Railway…
                        </p>
                    )}
                    {logs && visible.length === 0 && <p className="text-muted-foreground">Sin líneas.</p>}
                    {visible.map((line, i) => (
                        <div key={i} className={cn('break-all whitespace-pre-wrap', line.severity === 'error' && 'text-destructive')}>
                            <span className="text-muted-foreground mr-2">{new Date(line.timestamp).toLocaleTimeString('es-CO')}</span>
                            {line.message}
                        </div>
                    ))}
                </div>
            </DialogContent>
        </Dialog>
    );
}
