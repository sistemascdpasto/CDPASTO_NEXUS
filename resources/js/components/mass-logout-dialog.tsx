import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/react';
import { LoaderCircle, LogOut } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';

export interface MassLogoutApp {
    id: number;
    name: string;
    sessions: number;
}

/**
 * Cierre masivo de sesiones en las apps con login (solo superadmin). Pide contraseña y 2FA.
 */
export function MassLogoutDialog({ apps }: { apps: MassLogoutApp[] }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        applications: apps.map((a) => a.id),
        reason: '',
        password: '',
        code: '',
    });

    const toggle = (id: number, checked: boolean) =>
        setData('applications', checked ? [...data.applications, id] : data.applications.filter((x) => x !== id));

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/sessions/logout-all', {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                reset();
            },
        });
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                setOpen(value);
                if (!value) clearErrors();
            }}
        >
            <DialogTrigger asChild>
                <Button variant="destructive" size="sm">
                    <LogOut className="size-4" /> Cerrar todas las sesiones
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Cierre masivo de sesiones</DialogTitle>
                <DialogDescription>
                    Todos los usuarios de las apps marcadas quedan desconectados de inmediato y deben iniciar sesión otra vez. También se anulan las
                    opciones de «recordarme».
                </DialogDescription>
                <form onSubmit={submit} className="space-y-4">
                    <fieldset className="grid gap-2">
                        <legend className="mb-1 text-sm font-medium">Aplicaciones</legend>
                        {apps.map((app) => (
                            <label key={app.id} className="flex items-center gap-2 text-sm">
                                <Checkbox checked={data.applications.includes(app.id)} onCheckedChange={(c) => toggle(app.id, c === true)} />
                                <span className="flex-1">{app.name}</span>
                                <span className="text-muted-foreground text-xs">{app.sessions} conectados</span>
                            </label>
                        ))}
                        <InputError message={errors.applications} />
                    </fieldset>
                    <div className="grid gap-2">
                        <Label htmlFor="mass-reason">Motivo (queda en la auditoría)</Label>
                        <Input
                            id="mass-reason"
                            value={data.reason}
                            onChange={(e) => setData('reason', e.target.value)}
                            placeholder="Ej. posible robo de credenciales"
                        />
                        <InputError message={errors.reason} />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="mass-password">Tu contraseña</Label>
                            <Input
                                id="mass-password"
                                type="password"
                                autoComplete="current-password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                            />
                            <InputError message={errors.password} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="mass-code">Código del autenticador</Label>
                            <Input
                                id="mass-code"
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
                        <Button type="submit" variant="destructive" disabled={processing || data.applications.length === 0}>
                            {processing && <LoaderCircle className="animate-spin" />}
                            Cerrar sesiones
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
