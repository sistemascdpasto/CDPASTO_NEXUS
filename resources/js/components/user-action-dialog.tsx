import InputError from '@/components/input-error';
import { NativeSelect } from '@/components/nexus-ui';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/react';
import { Ban, LoaderCircle, LogOut, MoreHorizontal, Unlock } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';

type CommandType = 'logout' | 'block' | 'unblock';

interface Target {
    applicationId: number;
    applicationName?: string;
    externalUserId: string;
    name: string | null;
}

const copy: Record<CommandType, { title: string; description: string; confirm: string }> = {
    logout: {
        title: 'Cerrar sesión remotamente',
        description: 'Se eliminarán todas las sesiones abiertas del usuario en la aplicación. Podrá volver a ingresar con sus credenciales.',
        confirm: 'Cerrar sesión',
    },
    block: {
        title: 'Bloquear usuario',
        description: 'Se cerrarán sus sesiones y no podrá ingresar a la aplicación hasta que se desbloquee o venza el tiempo indicado.',
        confirm: 'Bloquear',
    },
    unblock: {
        title: 'Desbloquear usuario',
        description: 'El usuario podrá volver a ingresar a la aplicación.',
        confirm: 'Desbloquear',
    },
};

/**
 * Menú de acciones sobre un usuario de una app (HU-14, HU-15).
 */
export function UserActionsMenu({ target, blocked = false }: { target: Target; blocked?: boolean }) {
    const [action, setAction] = useState<CommandType | null>(null);

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="ghost" size="icon" aria-label="Acciones">
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem onSelect={() => setAction('logout')}>
                        <LogOut /> Cerrar sesión
                    </DropdownMenuItem>
                    {blocked ? (
                        <DropdownMenuItem onSelect={() => setAction('unblock')}>
                            <Unlock /> Desbloquear
                        </DropdownMenuItem>
                    ) : (
                        <DropdownMenuItem onSelect={() => setAction('block')} className="text-destructive">
                            <Ban /> Bloquear
                        </DropdownMenuItem>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>
            {action && <UserActionDialog target={target} type={action} open onOpenChange={(open) => !open && setAction(null)} />}
        </>
    );
}

export function UnblockButton({ target }: { target: Target }) {
    return (
        <UserActionDialog
            target={target}
            type="unblock"
            trigger={
                <Button variant="outline" size="sm">
                    <Unlock /> Desbloquear
                </Button>
            }
        />
    );
}

function UserActionDialog({
    target,
    type,
    open,
    onOpenChange,
    trigger,
}: {
    target: Target;
    type: CommandType;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    trigger?: React.ReactNode;
}) {
    const [internalOpen, setInternalOpen] = useState(false);
    const isOpen = open ?? internalOpen;
    const setOpen = onOpenChange ?? setInternalOpen;

    const { data, setData, post, processing, errors } = useForm({
        type,
        external_user_id: target.externalUserId,
        target_name: target.name ?? '',
        minutes: '',
        reason: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`/applications/${target.applicationId}/commands`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    const text = copy[type];

    return (
        <Dialog open={isOpen} onOpenChange={setOpen}>
            {trigger && <DialogTrigger asChild>{trigger}</DialogTrigger>}
            <DialogContent>
                <DialogTitle>{text.title}</DialogTitle>
                <DialogDescription>
                    <strong className="text-foreground">{target.name ?? `Usuario #${target.externalUserId}`}</strong>
                    {target.applicationName && <> en {target.applicationName}</>}. {text.description}
                </DialogDescription>
                <form onSubmit={submit} className="space-y-4">
                    {type === 'block' && (
                        <div className="grid gap-2">
                            <Label htmlFor="minutes">Duración</Label>
                            <NativeSelect id="minutes" value={data.minutes} onChange={(e) => setData('minutes', e.target.value)}>
                                <option value="">Hasta desbloquear manualmente</option>
                                <option value="30">30 minutos</option>
                                <option value="120">2 horas</option>
                                <option value="1440">24 horas</option>
                                <option value="10080">7 días</option>
                            </NativeSelect>
                            <InputError message={errors.minutes} />
                        </div>
                    )}
                    {type !== 'unblock' && (
                        <div className="grid gap-2">
                            <Label htmlFor="reason">Motivo (queda en la auditoría)</Label>
                            <Input
                                id="reason"
                                value={data.reason}
                                onChange={(e) => setData('reason', e.target.value)}
                                placeholder="Ej. acceso no autorizado"
                            />
                            <InputError message={errors.reason} />
                        </div>
                    )}
                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                Cancelar
                            </Button>
                        </DialogClose>
                        <Button type="submit" variant={type === 'unblock' ? 'default' : 'destructive'} disabled={processing}>
                            {processing && <LoaderCircle className="animate-spin" />}
                            {text.confirm}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
