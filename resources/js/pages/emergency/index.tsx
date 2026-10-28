import InputError from '@/components/input-error';
import { EmptyState, PageHeader, Panel, Table } from '@/components/nexus-ui';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { Head, router, useForm } from '@inertiajs/react';
import { Database, LoaderCircle, Plug, PowerOff, RefreshCw, ShieldAlert, ShieldCheck, TriangleAlert } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';

interface RailwayService {
    id: string;
    name: string;
    deployment_id: string | null;
    status: string | null;
    deployed_at: string | null;
}

interface LockdownService {
    service_id: string;
    name: string;
    state: 'stopped' | 'skipped' | 'failed' | 'reconnected';
    stopped_at: string;
    reconnected_at?: string;
    error: string | null;
}

interface Lockdown {
    id: number;
    status: 'active' | 'released';
    reason: string;
    started_at: string;
    released_at: string | null;
    services: LockdownService[];
    starter: { id: number; name: string } | null;
    releaser?: { id: number; name: string } | null;
}

interface Props {
    services: RailwayService[];
    railwayError: string | null;
    current: Lockdown | null;
    history: Lockdown[];
    protected: string[];
    projectId: string | null;
}

const railwayStatus: Record<string, { label: string; color: string }> = {
    SUCCESS: { label: 'En línea', color: 'var(--status-good)' },
    REMOVED: { label: 'Detenido', color: 'var(--status-critical)' },
    REMOVING: { label: 'Deteniendo…', color: 'var(--status-warning)' },
    DEPLOYING: { label: 'Levantando…', color: 'var(--status-warning)' },
    BUILDING: { label: 'Compilando…', color: 'var(--status-warning)' },
    INITIALIZING: { label: 'Iniciando…', color: 'var(--status-warning)' },
    QUEUED: { label: 'En cola', color: 'var(--status-warning)' },
    CRASHED: { label: 'Caído', color: 'var(--status-critical)' },
    FAILED: { label: 'Falló', color: 'var(--status-critical)' },
    SLEEPING: { label: 'Dormido', color: 'var(--status-unknown)' },
};

const stateLabel: Record<LockdownService['state'], string> = {
    stopped: 'Detenido',
    skipped: 'Omitido',
    failed: 'No se pudo detener',
    reconnected: 'Reconectado',
};

export default function EmergencyIndex({ services, railwayError, current, history, protected: protectedServices, projectId }: Props) {
    const [selected, setSelected] = useState<string[]>([]);
    const [dialog, setDialog] = useState<'disconnect' | 'reconnect' | null>(null);
    const stopped = current?.services.filter((s) => s.state === 'stopped') ?? [];
    const active = current !== null;

    const toggle = (id: string, checked: boolean) => setSelected((prev) => (checked ? [...prev, id] : prev.filter((x) => x !== id)));

    return (
        <AppLayout breadcrumbs={[{ title: 'Botón de emergencia', href: '/emergency' }]}>
            <Head title="Botón de emergencia" />
            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title="Botón de emergencia"
                    description="Ante un ataque, desconecta los sistemas publicados en Railway para que dejen de estar en línea. Las bases de datos y Nexus nunca se apagan."
                    actions={
                        <Button variant="outline" size="sm" onClick={() => router.reload({ only: ['services', 'current', 'railwayError'] })}>
                            <RefreshCw className="size-4" /> Actualizar estado
                        </Button>
                    }
                />

                {active ? (
                    <div className="flex flex-col gap-4 rounded-xl border-2 border-red-600 bg-red-50 p-5 sm:flex-row sm:items-center dark:bg-red-950/40">
                        <ShieldAlert className="size-10 shrink-0 text-red-600" aria-hidden />
                        <div className="flex-1 space-y-1">
                            <p className="text-lg font-semibold text-red-700 dark:text-red-400">MODO EMERGENCIA ACTIVO</p>
                            <p className="text-sm">
                                {current.starter?.name ?? 'Alguien'} desconectó los sistemas el {formatDateTime(current.started_at)}. Motivo:{' '}
                                {current.reason}
                            </p>
                            <p className="text-muted-foreground text-sm">
                                {stopped.length} servicio(s) detenidos: {stopped.map((s) => s.name).join(', ') || 'ninguno'}.
                            </p>
                        </div>
                        <Button
                            size="lg"
                            className="bg-green-600 text-white hover:bg-green-700"
                            onClick={() => setDialog('reconnect')}
                            disabled={stopped.length === 0}
                        >
                            <Plug className="size-5" /> Reconectar todo
                        </Button>
                    </div>
                ) : (
                    <div className="bg-card flex flex-col gap-4 rounded-xl border p-5 sm:flex-row sm:items-center">
                        <ShieldCheck className="size-10 shrink-0" style={{ color: 'var(--status-good)' }} aria-hidden />
                        <div className="flex-1 space-y-1">
                            <p className="text-lg font-semibold">Sistemas en operación normal</p>
                            <p className="text-muted-foreground text-sm">
                                Al desconectar, cada sistema queda fuera de línea en segundos. Reconectar los levanta de nuevo con la misma versión en
                                1 o 2 minutos, sin perder datos.
                            </p>
                        </div>
                        <Button size="lg" variant="destructive" onClick={() => setDialog('disconnect')} disabled={services.length === 0}>
                            <PowerOff className="size-5" /> {selected.length ? `Desconectar ${selected.length} seleccionado(s)` : 'Desconectar todo'}
                        </Button>
                    </div>
                )}

                {railwayError && (
                    <div className="flex items-start gap-2 rounded-xl border border-amber-500/50 bg-amber-50 p-4 text-sm dark:bg-amber-950/30">
                        <TriangleAlert className="mt-0.5 size-4 shrink-0 text-amber-600" /> {railwayError} Si es urgente, usa el Plan B de abajo.
                    </div>
                )}

                <Panel
                    title="Servicios que controla el botón"
                    description={
                        active ? 'Puedes reconectar uno por uno.' : 'Marca algunos para desconectar solo esos; sin marcar se desconectan todos.'
                    }
                >
                    {services.length === 0 ? (
                        <EmptyState icon={Plug} title="No hay servicios para mostrar" />
                    ) : (
                        <Table>
                            <thead>
                                <tr>
                                    {!active && <th className="w-8" />}
                                    <th>Servicio</th>
                                    <th>Estado en Railway</th>
                                    <th>Último despliegue</th>
                                    {active && <th className="text-right">Acción</th>}
                                </tr>
                            </thead>
                            <tbody>
                                {services.map((s) => {
                                    const status = railwayStatus[s.status ?? ''] ?? {
                                        label: s.status ?? 'Sin despliegues',
                                        color: 'var(--status-unknown)',
                                    };
                                    const entry = current?.services.find((e) => e.service_id === s.id);

                                    return (
                                        <tr key={s.id}>
                                            {!active && (
                                                <td>
                                                    <Checkbox
                                                        checked={selected.includes(s.id)}
                                                        onCheckedChange={(c) => toggle(s.id, c === true)}
                                                        aria-label={`Seleccionar ${s.name}`}
                                                    />
                                                </td>
                                            )}
                                            <td className="font-medium">{s.name}</td>
                                            <td>
                                                <span className="flex items-center gap-1.5">
                                                    <span className="size-2 rounded-full" style={{ backgroundColor: status.color }} />
                                                    {status.label}
                                                </span>
                                                {entry?.error && <span className="text-muted-foreground block text-xs">{entry.error}</span>}
                                            </td>
                                            <td className="text-muted-foreground">{s.deployed_at ? formatDateTime(s.deployed_at) : '—'}</td>
                                            {active && (
                                                <td className="text-right">
                                                    {entry?.state === 'stopped' && (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => {
                                                                setSelected([s.id]);
                                                                setDialog('reconnect');
                                                            }}
                                                        >
                                                            <Plug className="size-3.5" /> Reconectar
                                                        </Button>
                                                    )}
                                                </td>
                                            )}
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </Table>
                    )}
                    <p className="text-muted-foreground mt-3 flex flex-wrap items-center gap-1.5 text-xs">
                        <Database className="size-3.5" /> Nunca se apagan: las bases de datos y {protectedServices.join(', ')}.
                    </p>
                </Panel>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Panel title="Historial" description="Últimas 10 activaciones del botón.">
                        {history.length === 0 ? (
                            <EmptyState icon={ShieldCheck} title="El botón nunca se ha usado" />
                        ) : (
                            <ul className="divide-y text-sm">
                                {history.map((h) => (
                                    <li key={h.id} className="space-y-1 py-2.5">
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="font-medium">{formatDateTime(h.started_at)}</span>
                                            <span
                                                className={cn(
                                                    'rounded-full px-2 py-0.5 text-xs',
                                                    h.status === 'active' ? 'bg-red-600 text-white' : 'bg-accent',
                                                )}
                                            >
                                                {h.status === 'active' ? 'Activa' : 'Finalizada'}
                                            </span>
                                        </div>
                                        <p className="text-muted-foreground">
                                            {h.starter?.name ?? '—'}: {h.reason}
                                        </p>
                                        <p className="text-muted-foreground text-xs">
                                            {h.services.map((s) => `${s.name} (${stateLabel[s.state]})`).join(' · ')}
                                            {h.released_at && ` — reconectó ${h.releaser?.name ?? '—'} el ${formatDateTime(h.released_at)}`}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>

                    <Panel title="Plan B: si Nexus no responde" description="Para cuando el atacante tumbó o tomó el propio Nexus.">
                        <ol className="list-decimal space-y-2 pl-5 text-sm">
                            <li>
                                Entra a{' '}
                                <a
                                    className="underline"
                                    href={projectId ? `https://railway.com/project/${projectId}` : 'https://railway.com/dashboard'}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    railway.com
                                </a>{' '}
                                → proyecto <strong>blissful-prosperity</strong>.
                            </li>
                            <li>
                                En cada sistema: pestaña <strong>Deployments</strong> → menú <strong>⋮</strong> del despliegue activo →{' '}
                                <strong>Remove</strong>. No toques los servicios MySQL.
                            </li>
                            <li>
                                Con la CLI: <code className="bg-muted rounded px-1">railway down --service NOMBRE</code> por cada sistema.
                            </li>
                            <li>
                                Para volver: en el despliegue detenido → <strong>Redeploy</strong>, o{' '}
                                <code className="bg-muted rounded px-1">railway redeploy --service NOMBRE</code>.
                            </li>
                            <li>
                                Si sospechas que robaron credenciales: cambia la contraseña de Railway y GitHub, y regenera los tokens (incluido
                                RAILWAY_API_TOKEN de Nexus).
                            </li>
                        </ol>
                    </Panel>
                </div>
            </div>

            {dialog && (
                <ConfirmDialog
                    mode={dialog}
                    services={dialog === 'disconnect' ? selected : selected.length && active ? selected : []}
                    names={
                        dialog === 'disconnect'
                            ? (selected.length ? services.filter((s) => selected.includes(s.id)) : services).map((s) => s.name)
                            : (selected.length ? stopped.filter((s) => selected.includes(s.service_id)) : stopped).map((s) => s.name)
                    }
                    onClose={() => {
                        setDialog(null);
                        setSelected([]);
                    }}
                />
            )}
        </AppLayout>
    );
}

function ConfirmDialog({
    mode,
    services,
    names,
    onClose,
}: {
    mode: 'disconnect' | 'reconnect';
    services: string[];
    names: string[];
    onClose: () => void;
}) {
    const disconnect = mode === 'disconnect';
    const { data, setData, post, processing, errors } = useForm({
        reason: '',
        confirmation: '',
        password: '',
        code: '',
        services,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(disconnect ? '/emergency/disconnect' : '/emergency/reconnect', { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogTitle className={cn(disconnect && 'text-red-600')}>{disconnect ? 'Desconectar sistemas' : 'Reconectar sistemas'}</DialogTitle>
                <DialogDescription>
                    {disconnect ? 'Quedarán fuera de línea' : 'Volverán a estar en línea'}:{' '}
                    <strong className="text-foreground">{names.join(', ')}</strong>.
                    {disconnect && ' Los usuarios conectados perderán el acceso de inmediato.'}
                </DialogDescription>
                <form onSubmit={submit} className="space-y-4">
                    {disconnect && (
                        <div className="grid gap-2">
                            <Label htmlFor="reason">Motivo (queda en la auditoría y en la alerta)</Label>
                            <Textarea
                                id="reason"
                                value={data.reason}
                                onChange={(e) => setData('reason', e.target.value)}
                                placeholder="Ej. accesos sospechosos en Adenar"
                            />
                            <InputError message={errors.reason} />
                        </div>
                    )}
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="password">Tu contraseña</Label>
                            <Input
                                id="password"
                                type="password"
                                autoComplete="current-password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                            />
                            <InputError message={errors.password} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="code">Código del autenticador</Label>
                            <Input
                                id="code"
                                inputMode="numeric"
                                autoComplete="one-time-code"
                                maxLength={6}
                                value={data.code}
                                onChange={(e) => setData('code', e.target.value)}
                            />
                            <InputError message={errors.code} />
                        </div>
                    </div>
                    {disconnect && (
                        <div className="grid gap-2">
                            <Label htmlFor="confirmation">
                                Escribe <strong>DESCONECTAR</strong> para confirmar
                            </Label>
                            <Input id="confirmation" value={data.confirmation} onChange={(e) => setData('confirmation', e.target.value)} />
                            <InputError message={errors.confirmation} />
                        </div>
                    )}
                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                Cancelar
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            variant={disconnect ? 'destructive' : 'default'}
                            className={cn(!disconnect && 'bg-green-600 text-white hover:bg-green-700')}
                            disabled={processing || (disconnect && data.confirmation !== 'DESCONECTAR')}
                        >
                            {processing && <LoaderCircle className="animate-spin" />}
                            {disconnect ? 'Desconectar' : 'Reconectar'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
