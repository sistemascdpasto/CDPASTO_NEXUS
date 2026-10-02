import { Field } from '@/components/form-field';
import { NativeSelect, PageHeader, Panel } from '@/components/nexus-ui';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { type FormEventHandler } from 'react';

interface FormApp {
    id?: number;
    name?: string;
    slug?: string;
    description?: string | null;
    type: 'laravel' | 'static';
    url?: string;
    health_path?: string | null;
    environment: string;
    railway_service_id?: string | null;
    railway_service_name?: string | null;
    database_service_id?: string | null;
    database_service_name?: string | null;
    repository?: string | null;
    check_interval_minutes: number;
    slow_threshold_ms: number;
    error_threshold: number;
    failed_login_threshold: number;
    mass_delete_threshold: number;
    is_active: boolean;
    user_ids: number[];
}

interface Props {
    application: FormApp;
    railwayServices: { id: string; name: string }[];
    users: { id: number; name: string; email: string; role: string }[];
}

export default function ApplicationForm({ application, railwayServices, users }: Props) {
    const editing = Boolean(application.id);
    const { data, setData, post, put, processing, errors } = useForm({
        name: application.name ?? '',
        slug: application.slug ?? '',
        description: application.description ?? '',
        type: application.type,
        url: application.url ?? '',
        health_path: application.health_path ?? '',
        environment: application.environment,
        railway_service_id: application.railway_service_id ?? '',
        railway_service_name: application.railway_service_name ?? '',
        database_service_id: application.database_service_id ?? '',
        database_service_name: application.database_service_name ?? '',
        repository: application.repository ?? '',
        check_interval_minutes: application.check_interval_minutes,
        slow_threshold_ms: application.slow_threshold_ms,
        error_threshold: application.error_threshold,
        failed_login_threshold: application.failed_login_threshold,
        mass_delete_threshold: application.mass_delete_threshold,
        is_active: application.is_active,
        user_ids: application.user_ids,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (editing) {
            put(`/applications/${application.id}`);
        } else {
            post('/applications');
        }
    };

    const title = editing ? `Editar ${application.name}` : 'Registrar aplicación';

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Aplicaciones', href: '/applications' },
                { title, href: '#' },
            ]}
        >
            <Head title={title} />
            <form onSubmit={submit} className="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4">
                <PageHeader
                    title={title}
                    description={editing ? undefined : 'Al guardar se genera la API key que usará el agente de la app para enviar datos.'}
                />

                <Panel title="Información general">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Nombre" error={errors.name}>
                            <Input value={data.name} onChange={(e) => setData('name', e.target.value)} required autoFocus />
                        </Field>
                        <Field label="Identificador (slug)" error={errors.slug} hint="Se genera del nombre si se deja vacío">
                            <Input value={data.slug} onChange={(e) => setData('slug', e.target.value)} placeholder="adenar" />
                        </Field>
                        <Field label="URL pública" error={errors.url} className="sm:col-span-2">
                            <Input
                                type="url"
                                value={data.url}
                                onChange={(e) => setData('url', e.target.value)}
                                placeholder="https://miapp.up.railway.app"
                                required
                            />
                        </Field>
                        <Field label="Tipo" error={errors.type}>
                            <NativeSelect value={data.type} onChange={(e) => setData('type', e.target.value as FormApp['type'])} className="w-full">
                                <option value="laravel">Laravel (con agente Nexus)</option>
                                <option value="static">Sitio estático (solo disponibilidad)</option>
                            </NativeSelect>
                        </Field>
                        <Field label="Entorno" error={errors.environment}>
                            <NativeSelect value={data.environment} onChange={(e) => setData('environment', e.target.value)} className="w-full">
                                <option value="production">Producción</option>
                                <option value="staging">Pruebas</option>
                                <option value="development">Desarrollo</option>
                            </NativeSelect>
                        </Field>
                        <Field label="Descripción" error={errors.description} className="sm:col-span-2">
                            <Input value={data.description} onChange={(e) => setData('description', e.target.value)} />
                        </Field>
                    </div>
                </Panel>

                <Panel title="Railway y repositorio">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            label="Servicio de Railway"
                            error={errors.railway_service_id}
                            hint={railwayServices.length === 0 ? 'Configura RAILWAY_API_TOKEN para listar los servicios' : undefined}
                        >
                            <NativeSelect
                                className="w-full"
                                value={data.railway_service_id}
                                onChange={(e) => {
                                    const service = railwayServices.find((s) => s.id === e.target.value);
                                    setData((d) => ({ ...d, railway_service_id: e.target.value, railway_service_name: service?.name ?? '' }));
                                }}
                            >
                                <option value="">Sin vincular</option>
                                {data.railway_service_id && !railwayServices.some((s) => s.id === data.railway_service_id) && (
                                    <option value={data.railway_service_id}>{data.railway_service_name || data.railway_service_id}</option>
                                )}
                                {railwayServices.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.name}
                                    </option>
                                ))}
                            </NativeSelect>
                        </Field>
                        {data.type === 'laravel' && (
                            <Field
                                label="Base de datos (MySQL en Railway)"
                                error={errors.database_service_id}
                                hint="Nexus lee las credenciales en vivo desde Railway y se conecta en solo lectura"
                            >
                                <NativeSelect
                                    className="w-full"
                                    value={data.database_service_id}
                                    onChange={(e) => {
                                        const service = railwayServices.find((s) => s.id === e.target.value);
                                        setData((d) => ({ ...d, database_service_id: e.target.value, database_service_name: service?.name ?? '' }));
                                    }}
                                >
                                    <option value="">Sin base de datos vinculada</option>
                                    {data.database_service_id && !railwayServices.some((s) => s.id === data.database_service_id) && (
                                        <option value={data.database_service_id}>{data.database_service_name || data.database_service_id}</option>
                                    )}
                                    {railwayServices
                                        .filter((s) => /mysql|postgres|maria/i.test(s.name))
                                        .map((s) => (
                                            <option key={s.id} value={s.id}>
                                                {s.name}
                                            </option>
                                        ))}
                                </NativeSelect>
                            </Field>
                        )}
                        <Field label="Repositorio de GitHub" error={errors.repository} hint="organización/repositorio">
                            <Input
                                value={data.repository}
                                onChange={(e) => setData('repository', e.target.value)}
                                placeholder="sistemascdpasto/mi-repo"
                            />
                        </Field>
                    </div>
                </Panel>

                <Panel title="Monitoreo y umbrales de alerta">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Verificar cada" error={errors.check_interval_minutes}>
                            <NativeSelect
                                className="w-full"
                                value={data.check_interval_minutes}
                                onChange={(e) => setData('check_interval_minutes', Number(e.target.value))}
                            >
                                {[1, 2, 5, 10, 15, 30, 60].map((m) => (
                                    <option key={m} value={m}>
                                        {m === 1 ? '1 minuto' : `${m} minutos`}
                                    </option>
                                ))}
                            </NativeSelect>
                        </Field>
                        <Field
                            label="Ruta de salud"
                            error={errors.health_path}
                            hint={
                                data.type === 'laravel'
                                    ? 'Vacío = /nexus/health (agente). Usa /up mientras no esté instalado.'
                                    : 'Vacío = página principal'
                            }
                        >
                            <Input
                                value={data.health_path}
                                onChange={(e) => setData('health_path', e.target.value)}
                                placeholder={data.type === 'laravel' ? '/nexus/health' : '/'}
                            />
                        </Field>
                        <Field label="Respuesta lenta desde (ms)" error={errors.slow_threshold_ms} hint="Por encima se marca como degradada">
                            <Input
                                type="number"
                                min={200}
                                value={data.slow_threshold_ms}
                                onChange={(e) => setData('slow_threshold_ms', Number(e.target.value))}
                            />
                        </Field>
                        {data.type === 'laravel' && (
                            <>
                                <Field label="Alertar con errores ≥" error={errors.error_threshold} hint="Excepciones en 10 minutos">
                                    <Input
                                        type="number"
                                        min={1}
                                        value={data.error_threshold}
                                        onChange={(e) => setData('error_threshold', Number(e.target.value))}
                                    />
                                </Field>
                                <Field label="Alertar con logins fallidos ≥" error={errors.failed_login_threshold} hint="Intentos en 10 minutos">
                                    <Input
                                        type="number"
                                        min={1}
                                        value={data.failed_login_threshold}
                                        onChange={(e) => setData('failed_login_threshold', Number(e.target.value))}
                                    />
                                </Field>
                                <Field
                                    label="Alertar con eliminaciones ≥"
                                    error={errors.mass_delete_threshold}
                                    hint="Por un mismo usuario en 10 minutos"
                                >
                                    <Input
                                        type="number"
                                        min={1}
                                        value={data.mass_delete_threshold}
                                        onChange={(e) => setData('mass_delete_threshold', Number(e.target.value))}
                                    />
                                </Field>
                            </>
                        )}
                    </div>
                    <label className="mt-4 flex items-center gap-2 text-sm">
                        <Checkbox checked={data.is_active} onCheckedChange={(v) => setData('is_active', v === true)} />
                        Monitoreo activo
                    </label>
                </Panel>

                <Panel
                    title="Responsables"
                    description="Jefes y encargados que pueden ver esta app y reciben sus alertas. Los superadministradores ven todas."
                >
                    {users.length === 0 ? (
                        <p className="text-muted-foreground text-sm">
                            Aún no hay usuarios.{' '}
                            <Link href="/users/create" className="underline">
                                Crear usuario
                            </Link>
                        </p>
                    ) : (
                        <div className="grid gap-2 sm:grid-cols-2">
                            {users.map((u) => (
                                <label key={u.id} className="hover:bg-accent/50 flex items-center gap-2 rounded-md border p-2 text-sm">
                                    <Checkbox
                                        checked={data.user_ids.includes(u.id)}
                                        onCheckedChange={(v) =>
                                            setData('user_ids', v === true ? [...data.user_ids, u.id] : data.user_ids.filter((id) => id !== u.id))
                                        }
                                    />
                                    <span className="min-w-0">
                                        <span className="block truncate font-medium">{u.name}</span>
                                        <span className="text-muted-foreground block truncate text-xs">
                                            {u.role === 'jefe' ? 'Jefe / Encargado' : 'Solo lectura'} · {u.email}
                                        </span>
                                    </span>
                                </label>
                            ))}
                        </div>
                    )}
                </Panel>

                <div className="flex justify-end gap-2">
                    <Button variant="secondary" asChild>
                        <Link href={editing ? `/applications/${application.id}` : '/applications'}>Cancelar</Link>
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing && <LoaderCircle className="animate-spin" />}
                        {editing ? 'Guardar cambios' : 'Registrar y generar API key'}
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
