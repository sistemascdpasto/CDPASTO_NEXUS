import { EmptyState, PageHeader, Panel, Table, applyFilters } from '@/components/nexus-ui';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { formatRelative } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type AppRef, type Role } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Bell, BellOff, Pencil, Plus, ShieldCheck, ShieldOff, Users } from 'lucide-react';
import { useState } from 'react';

interface PanelUser {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    role: Role;
    role_label: string;
    is_active: boolean;
    receive_alerts: boolean;
    two_factor: boolean;
    last_login_at: string | null;
    last_login_ip: string | null;
    applications: AppRef[];
}

export default function UsersIndex({ users, filters }: { users: PanelUser[]; filters: { search?: string | null } }) {
    const [search, setSearch] = useState(filters.search ?? '');

    return (
        <AppLayout breadcrumbs={[{ title: 'Usuarios del panel', href: '/users' }]}>
            <Head title="Usuarios del panel" />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title="Usuarios del panel"
                    description="Jefes y encargados con acceso a Nexus. Cada uno ve solo las aplicaciones que tiene asignadas."
                    actions={
                        <Button asChild>
                            <Link href="/users/create">
                                <Plus /> Nuevo usuario
                            </Link>
                        </Button>
                    }
                />

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        applyFilters('/users', { search });
                    }}
                >
                    <Input
                        className="h-9 max-w-sm"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Buscar por nombre o correo…"
                    />
                </form>

                <Panel>
                    {users.length === 0 ? (
                        <EmptyState icon={Users} title="Sin usuarios" />
                    ) : (
                        <Table className="-m-4">
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Rol</th>
                                    <th>Aplicaciones</th>
                                    <th>Seguridad</th>
                                    <th>Último ingreso</th>
                                    <th />
                                </tr>
                            </thead>
                            <tbody>
                                {users.map((u) => (
                                    <tr key={u.id} className={cn(!u.is_active && 'opacity-50')}>
                                        <td>
                                            <div className="font-medium">
                                                {u.name} {!u.is_active && <span className="text-muted-foreground text-xs">(inactivo)</span>}
                                            </div>
                                            <div className="text-muted-foreground text-xs">{u.email}</div>
                                        </td>
                                        <td className="text-xs whitespace-nowrap">{u.role_label}</td>
                                        <td className="max-w-xs text-xs">
                                            {u.role === 'superadmin' ? (
                                                <span className="text-muted-foreground">Todas</span>
                                            ) : u.applications.length ? (
                                                u.applications.map((a) => a.name).join(', ')
                                            ) : (
                                                <span className="text-muted-foreground">Ninguna asignada</span>
                                            )}
                                        </td>
                                        <td>
                                            <div className="flex items-center gap-2 text-xs">
                                                <span
                                                    className="inline-flex items-center gap-1"
                                                    title={u.two_factor ? '2FA activo' : '2FA pendiente'}
                                                >
                                                    {u.two_factor ? (
                                                        <ShieldCheck className="size-4" style={{ color: 'var(--status-good)' }} />
                                                    ) : (
                                                        <ShieldOff className="size-4" style={{ color: 'var(--status-warning)' }} />
                                                    )}
                                                    {u.two_factor ? '2FA' : 'Pendiente'}
                                                </span>
                                                <span title={u.receive_alerts ? 'Recibe alertas' : 'Sin alertas'}>
                                                    {u.receive_alerts ? (
                                                        <Bell className="size-4" />
                                                    ) : (
                                                        <BellOff className="text-muted-foreground size-4" />
                                                    )}
                                                </span>
                                            </div>
                                        </td>
                                        <td className="text-xs">
                                            {formatRelative(u.last_login_at)}
                                            {u.last_login_ip && <div className="text-muted-foreground font-mono">{u.last_login_ip}</div>}
                                        </td>
                                        <td className="text-right">
                                            <Button variant="ghost" size="icon" asChild>
                                                <Link href={`/users/${u.id}/edit`} aria-label="Editar">
                                                    <Pencil />
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </Table>
                    )}
                </Panel>
            </div>
        </AppLayout>
    );
}
