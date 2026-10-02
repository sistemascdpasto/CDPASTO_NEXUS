import { EmptyState, NativeSelect, PageHeader, Panel, Table, applyFilters } from '@/components/nexus-ui';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { UserActionsMenu } from '@/components/user-action-dialog';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime, formatRelative } from '@/lib/format';
import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, Ban, ChevronLeft, ChevronRight, Users } from 'lucide-react';
import { useState } from 'react';

interface DirectoryUser {
    id: string;
    name: string;
    email: string | null;
    document: string | null;
    roles: string[];
    is_active: boolean | null;
    deleted: boolean;
    created_at: string | null;
    last_login_at: string | null;
    last_activity_at: string | null;
    online: boolean;
}

interface Props {
    application: { id: number; name: string; database_service_name: string | null };
    directory: { users: DirectoryUser[]; total: number; page: number; last_page: number; columns: string[] } | null;
    known: { id: string; name: string | null; email: string | null; last_login_at: string }[] | null;
    filters: { search?: string; status?: string };
    error: string | null;
    canOperate: boolean;
    blockedIds: string[];
}

export default function ApplicationUsers({ application: app, directory, known, filters, error, canOperate, blockedIds }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const base = `/applications/${app.id}/users`;
    const update = (patch: Record<string, string | number | undefined>) => applyFilters(base, { ...filters, ...patch });

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Aplicaciones', href: '/applications' },
                { title: app.name, href: `/applications/${app.id}` },
                { title: 'Usuarios', href: base },
            ]}
        >
            <Head title={`Usuarios · ${app.name}`} />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title={`Usuarios de ${app.name}`}
                    description={
                        directory
                            ? `${directory.total} usuarios registrados en la base de datos ${app.database_service_name ?? ''}. Haz clic en uno para ver todo lo que ha hecho.`
                            : 'Usuarios vistos por el agente.'
                    }
                />

                {error && (
                    <div className="flex items-start gap-2 rounded-lg border p-3 text-sm" style={{ borderColor: 'var(--status-warning)' }}>
                        <AlertTriangle className="mt-0.5 size-4 shrink-0" style={{ color: 'var(--status-warning)' }} />
                        {error}
                    </div>
                )}

                {directory && (
                    <div className="flex flex-wrap gap-2">
                        <form
                            className="min-w-56 flex-1"
                            onSubmit={(e) => {
                                e.preventDefault();
                                update({ search, page: undefined });
                            }}
                        >
                            <Input
                                className="h-9"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Nombre, correo, documento o ID…"
                            />
                        </form>
                        {(directory.columns.includes('is_active') || directory.columns.includes('activo')) && (
                            <NativeSelect
                                value={filters.status ?? ''}
                                onChange={(e) => update({ status: e.target.value, page: undefined })}
                                aria-label="Estado"
                            >
                                <option value="">Todos</option>
                                <option value="active">Activos</option>
                                <option value="inactive">Inactivos</option>
                            </NativeSelect>
                        )}
                    </div>
                )}

                <Panel>
                    {directory ? (
                        directory.users.length === 0 ? (
                            <EmptyState icon={Users} title="Sin resultados" />
                        ) : (
                            <Table className="-m-4">
                                <thead>
                                    <tr>
                                        <th>Usuario</th>
                                        <th>Documento</th>
                                        <th>Rol</th>
                                        <th>Estado</th>
                                        <th>Último ingreso</th>
                                        <th>Última actividad</th>
                                        {canOperate && <th className="w-10" />}
                                    </tr>
                                </thead>
                                <tbody>
                                    {directory.users.map((u) => {
                                        const blocked = blockedIds.includes(u.id);

                                        return (
                                            <tr key={u.id}>
                                                <td>
                                                    <Link href={`${base}/${u.id}`} className="group flex items-center gap-2">
                                                        {u.online && (
                                                            <span
                                                                className="size-2 shrink-0 rounded-full"
                                                                style={{ background: 'var(--status-good)' }}
                                                                title="Conectado ahora"
                                                            />
                                                        )}
                                                        <span>
                                                            <span className="block font-medium group-hover:underline">{u.name}</span>
                                                            <span className="text-muted-foreground block text-xs">{u.email}</span>
                                                        </span>
                                                    </Link>
                                                </td>
                                                <td className="font-mono text-xs">{u.document ?? '—'}</td>
                                                <td className="text-xs">{u.roles.join(', ') || '—'}</td>
                                                <td className="text-xs">
                                                    {blocked ? (
                                                        <span className="inline-flex items-center gap-1" style={{ color: 'var(--status-critical)' }}>
                                                            <Ban className="size-3.5" /> Bloqueado
                                                        </span>
                                                    ) : u.deleted ? (
                                                        'Eliminado'
                                                    ) : u.is_active === false ? (
                                                        <span className="text-muted-foreground">Inactivo</span>
                                                    ) : (
                                                        'Activo'
                                                    )}
                                                </td>
                                                <td className="text-xs whitespace-nowrap" title={formatDateTime(u.last_login_at)}>
                                                    {u.last_login_at ? formatRelative(u.last_login_at) : '—'}
                                                </td>
                                                <td className="text-xs whitespace-nowrap">
                                                    {u.last_activity_at ? formatRelative(u.last_activity_at) : '—'}
                                                </td>
                                                {canOperate && (
                                                    <td className="text-right">
                                                        <UserActionsMenu
                                                            target={{
                                                                applicationId: app.id,
                                                                applicationName: app.name,
                                                                externalUserId: u.id,
                                                                name: u.name,
                                                            }}
                                                            blocked={blocked}
                                                        />
                                                    </td>
                                                )}
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </Table>
                        )
                    ) : known && known.length > 0 ? (
                        <ul className="-m-4 divide-y">
                            {known.map((u) => (
                                <li key={u.id}>
                                    <Link href={`${base}/${u.id}`} className="hover:bg-accent/40 flex justify-between gap-2 px-4 py-2.5 text-sm">
                                        <span>
                                            <span className="font-medium">{u.name ?? `#${u.id}`}</span>
                                            <span className="text-muted-foreground ml-2 text-xs">{u.email}</span>
                                        </span>
                                        <span className="text-muted-foreground text-xs">Último ingreso {formatRelative(u.last_login_at)}</span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <EmptyState icon={Users} title="Sin usuarios conocidos todavía" />
                    )}
                </Panel>

                {directory && directory.last_page > 1 && (
                    <div className="flex items-center justify-end gap-2 text-sm">
                        <Button
                            variant="outline"
                            size="icon"
                            disabled={directory.page <= 1}
                            onClick={() => update({ page: directory.page - 1 })}
                            aria-label="Anterior"
                        >
                            <ChevronLeft />
                        </Button>
                        <span className="text-muted-foreground text-xs">
                            Página {directory.page} de {directory.last_page}
                        </span>
                        <Button
                            variant="outline"
                            size="icon"
                            disabled={directory.page >= directory.last_page}
                            onClick={() => update({ page: directory.page + 1 })}
                            aria-label="Siguiente"
                        >
                            <ChevronRight />
                        </Button>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
