import { Field } from '@/components/form-field';
import { NativeSelect, PageHeader, Panel } from '@/components/nexus-ui';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type Role } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { LoaderCircle, ShieldCheck, ShieldOff, Trash2 } from 'lucide-react';
import { type FormEventHandler } from 'react';

interface Props {
    user: {
        id: number;
        name: string;
        email: string;
        phone: string | null;
        role: Role;
        is_active: boolean;
        receive_alerts: boolean;
        application_ids: number[];
        two_factor: boolean;
    } | null;
    roles: { value: Role; label: string }[];
    applications: { id: number; name: string; is_active: boolean }[];
    whatsappDriver: string | null;
}

const roleHelp: Record<Role, string> = {
    superadmin: 'Ve y administra todo: aplicaciones, usuarios del panel y auditoría del panel.',
    jefe: 'Ve sus aplicaciones asignadas y puede cerrar sesiones, bloquear usuarios y resolver errores.',
    lector: 'Solo consulta la información de sus aplicaciones asignadas.',
};

export default function UserForm({ user, roles, applications, whatsappDriver }: Props) {
    const editing = user !== null;
    const { data, setData, post, put, processing, errors } = useForm({
        name: user?.name ?? '',
        email: user?.email ?? '',
        phone: user?.phone ?? '',
        role: user?.role ?? ('jefe' as Role),
        is_active: user?.is_active ?? true,
        receive_alerts: user?.receive_alerts ?? true,
        password: '',
        password_confirmation: '',
        application_ids: user?.application_ids ?? ([] as number[]),
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (editing) {
            put(`/users/${user.id}`);
        } else {
            post('/users');
        }
    };

    const title = editing ? `Editar ${user.name}` : 'Nuevo usuario';

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Usuarios del panel', href: '/users' },
                { title, href: '#' },
            ]}
        >
            <Head title={title} />
            <form onSubmit={submit} className="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4">
                <PageHeader title={title} />

                <Panel title="Datos de acceso">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Nombre completo" error={errors.name}>
                            <Input value={data.name} onChange={(e) => setData('name', e.target.value)} required autoFocus />
                        </Field>
                        <Field label="Correo" error={errors.email}>
                            <Input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} required />
                        </Field>
                        <Field
                            label={editing ? 'Nueva contraseña (opcional)' : 'Contraseña'}
                            error={errors.password}
                            hint="Mínimo 10 caracteres con mayúsculas, minúsculas y números"
                        >
                            <Input
                                type="password"
                                autoComplete="new-password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                required={!editing}
                            />
                        </Field>
                        <Field label="Confirmar contraseña" error={errors.password_confirmation}>
                            <Input
                                type="password"
                                autoComplete="new-password"
                                value={data.password_confirmation}
                                onChange={(e) => setData('password_confirmation', e.target.value)}
                                required={!editing}
                            />
                        </Field>
                    </div>
                    <label className="mt-4 flex items-center gap-2 text-sm">
                        <Checkbox checked={data.is_active} onCheckedChange={(v) => setData('is_active', v === true)} />
                        Cuenta activa (si se desactiva, se cierran sus sesiones)
                    </label>
                </Panel>

                <Panel title="Rol y aplicaciones">
                    <Field label="Rol" error={errors.role} hint={roleHelp[data.role]}>
                        <NativeSelect className="w-full sm:w-72" value={data.role} onChange={(e) => setData('role', e.target.value as Role)}>
                            {roles.map((r) => (
                                <option key={r.value} value={r.value}>
                                    {r.label}
                                </option>
                            ))}
                        </NativeSelect>
                    </Field>

                    {data.role !== 'superadmin' && (
                        <div className="mt-4">
                            <p className="mb-2 text-sm font-medium">Aplicaciones asignadas</p>
                            <div className="grid gap-2 sm:grid-cols-2">
                                {applications.map((a) => (
                                    <label key={a.id} className="hover:bg-accent/50 flex items-center gap-2 rounded-md border p-2 text-sm">
                                        <Checkbox
                                            checked={data.application_ids.includes(a.id)}
                                            onCheckedChange={(v) =>
                                                setData(
                                                    'application_ids',
                                                    v === true ? [...data.application_ids, a.id] : data.application_ids.filter((id) => id !== a.id),
                                                )
                                            }
                                        />
                                        {a.name}
                                        {!a.is_active && <span className="text-muted-foreground text-xs">(inactiva)</span>}
                                    </label>
                                ))}
                            </div>
                            {errors.application_ids && <p className="text-destructive mt-1 text-sm">{errors.application_ids}</p>}
                        </div>
                    )}
                </Panel>

                <Panel title="Notificaciones">
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox checked={data.receive_alerts} onCheckedChange={(v) => setData('receive_alerts', v === true)} />
                        Recibir alertas de sus aplicaciones por correo{whatsappDriver ? ' y WhatsApp' : ''}
                    </label>
                    <div className="mt-4">
                        <Field
                            label="WhatsApp"
                            error={errors.phone}
                            hint={
                                whatsappDriver === 'callmebot'
                                    ? 'Formato: número|apikey de CallMeBot. Ej. 573001234567|123456'
                                    : 'Número con indicativo del país. Ej. 573001234567'
                            }
                        >
                            <Input
                                className="sm:w-72"
                                value={data.phone}
                                onChange={(e) => setData('phone', e.target.value)}
                                placeholder="573001234567"
                            />
                        </Field>
                    </div>
                </Panel>

                {editing && (
                    <Panel title="Seguridad">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <span className="inline-flex items-center gap-2 text-sm">
                                {user.two_factor ? (
                                    <ShieldCheck className="size-4" style={{ color: 'var(--status-good)' }} />
                                ) : (
                                    <ShieldOff className="size-4" style={{ color: 'var(--status-warning)' }} />
                                )}
                                {user.two_factor ? 'Doble factor activo' : 'Aún no ha configurado el doble factor'}
                            </span>
                            <div className="flex gap-2">
                                {user.two_factor && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            confirm(`¿Reiniciar el doble factor de ${user.name}? Deberá configurarlo de nuevo.`) &&
                                            router.post(`/users/${user.id}/reset-two-factor`)
                                        }
                                    >
                                        Reiniciar 2FA
                                    </Button>
                                )}
                                <Button
                                    type="button"
                                    variant="destructive"
                                    size="sm"
                                    onClick={() =>
                                        confirm(`¿Eliminar a ${user.name}? Perderá el acceso de inmediato.`) && router.delete(`/users/${user.id}`)
                                    }
                                >
                                    <Trash2 /> Eliminar
                                </Button>
                            </div>
                        </div>
                    </Panel>
                )}

                <div className="flex justify-end gap-2">
                    <Button variant="secondary" asChild>
                        <Link href="/users">Cancelar</Link>
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing && <LoaderCircle className="animate-spin" />}
                        {editing ? 'Guardar cambios' : 'Crear usuario'}
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
