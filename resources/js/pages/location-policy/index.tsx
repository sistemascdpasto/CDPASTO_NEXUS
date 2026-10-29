import InputError from '@/components/input-error';
import { PageHeader, Panel } from '@/components/nexus-ui';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { formatRelative } from '@/lib/format';
import { cn } from '@/lib/utils';
import { Head, router, useForm } from '@inertiajs/react';
import { LoaderCircle, LocateFixed, LocateOff } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';

interface PolicyApp {
    id: number;
    name: string;
    location_required: boolean;
    last_ingest_at: string | null;
    last_change: { required: boolean; by: string | null; at: string; reason: string | null; applied: boolean } | null;
}

function Switch({ checked, disabled, onChange, label }: { checked: boolean; disabled?: boolean; onChange: () => void; label: string }) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={checked}
            aria-label={label}
            disabled={disabled}
            onClick={onChange}
            className={cn(
                'relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition disabled:opacity-50',
                checked ? 'bg-primary' : 'bg-muted-foreground/30',
            )}
        >
            <span className={cn('bg-background inline-block size-5 rounded-full shadow transition', checked ? 'translate-x-5' : 'translate-x-0.5')} />
        </button>
    );
}

/**
 * Apagar la ubicación obligatoria: motivo + contraseña + código del autenticador.
 */
function DisableDialog({ app, onClose }: { app: PolicyApp | null; onClose: () => void }) {
    const { data, setData, put, processing, errors, reset, clearErrors } = useForm({ required: false, reason: '', password: '', code: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (!app) return;
        put(`/location-policy/${app.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
            },
        });
    };

    return (
        <Dialog
            open={app !== null}
            onOpenChange={(open) => {
                if (!open) {
                    clearErrors();
                    onClose();
                }
            }}
        >
            <DialogContent>
                <DialogTitle>Desactivar la ubicación obligatoria en {app?.name}</DialogTitle>
                <DialogDescription>
                    Los usuarios podrán usar el sistema sin compartir su ubicación. Si la niegan, en el mapa solo quedará la ciudad aproximada por su
                    IP.
                </DialogDescription>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="policy-reason">Motivo (queda en la auditoría)</Label>
                        <Input
                            id="policy-reason"
                            value={data.reason}
                            onChange={(e) => setData('reason', e.target.value)}
                            placeholder="Ej. equipos sin GPS en bodega"
                        />
                        <InputError message={errors.reason} />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="policy-password">Tu contraseña</Label>
                            <Input
                                id="policy-password"
                                type="password"
                                autoComplete="current-password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                            />
                            <InputError message={errors.password} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="policy-code">Código del autenticador</Label>
                            <Input
                                id="policy-code"
                                inputMode="numeric"
                                autoComplete="one-time-code"
                                maxLength={6}
                                value={data.code}
                                onChange={(e) => setData('code', e.target.value)}
                            />
                            <InputError message={errors.code} />
                        </div>
                    </div>
                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                Cancelar
                            </Button>
                        </DialogClose>
                        <Button type="submit" variant="destructive" disabled={processing}>
                            {processing && <LoaderCircle className="animate-spin" />}
                            Desactivar
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function LocationPolicy({ apps }: { apps: PolicyApp[] }) {
    const [disabling, setDisabling] = useState<PolicyApp | null>(null);
    const [enabling, setEnabling] = useState<number | null>(null);

    const enable = (app: PolicyApp) => {
        setEnabling(app.id);
        router.put(`/location-policy/${app.id}`, { required: true }, { preserveScroll: true, onFinish: () => setEnabling(null) });
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'Ubicación obligatoria', href: '/location-policy' }]}>
            <Head title="Ubicación obligatoria" />
            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title="Ubicación obligatoria"
                    description="Elige en qué sistemas hay que permitir la ubicación para usarlos. Con la opción activa, quien niegue el permiso ve un aviso y no puede entrar a las opciones del sistema hasta permitirla."
                />

                <Panel
                    title="Sistemas"
                    description="El cambio se aplica al instante; si la app no responde, lo toma en su próximo reporte (≈1 minuto)."
                >
                    <ul className="divide-y">
                        {apps.map((app) => (
                            <li key={app.id} className="flex flex-wrap items-center gap-4 py-3 first:pt-0 last:pb-0">
                                <span
                                    className={cn(
                                        'flex size-9 items-center justify-center rounded-lg',
                                        app.location_required ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground',
                                    )}
                                >
                                    {app.location_required ? <LocateFixed className="size-4" /> : <LocateOff className="size-4" />}
                                </span>
                                <div className="min-w-0 flex-1">
                                    <p className="font-medium">{app.name}</p>
                                    <p className="text-muted-foreground text-xs">
                                        {app.location_required ? 'Exige la ubicación para usar el sistema' : 'La ubicación es opcional'}
                                        {app.last_change && (
                                            <>
                                                {' · '}
                                                {app.last_change.required ? 'Activada' : 'Desactivada'} por {app.last_change.by ?? 'alguien'}{' '}
                                                {formatRelative(app.last_change.at)}
                                                {app.last_change.reason && ` — «${app.last_change.reason}»`}
                                                {!app.last_change.applied && ' (pendiente de que la app reporte)'}
                                            </>
                                        )}
                                    </p>
                                </div>
                                <Switch
                                    checked={app.location_required}
                                    disabled={enabling === app.id}
                                    label={`Ubicación obligatoria en ${app.name}`}
                                    onChange={() => (app.location_required ? setDisabling(app) : enable(app))}
                                />
                            </li>
                        ))}
                        {apps.length === 0 && <li className="text-muted-foreground text-sm">No hay sistemas con inicio de sesión.</li>}
                    </ul>
                </Panel>

                <p className="text-muted-foreground text-xs">
                    Desactivarla pide tu contraseña y el código del autenticador. Como respaldo, la variable{' '}
                    <code>NEXUS_LOCATION_REQUIRED=false</code> en Railway la apaga en una app aunque aquí esté activa.
                </p>
            </div>

            <DisableDialog app={disabling} onClose={() => setDisabling(null)} />
        </AppLayout>
    );
}
