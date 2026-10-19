import { Field } from '@/components/form-field';
import { PageHeader, Panel } from '@/components/nexus-ui';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { Head, Link, useForm } from '@inertiajs/react';
import { Check, Globe, ImageUp, LoaderCircle, LogIn, X } from 'lucide-react';
import { type FormEventHandler, useEffect, useMemo, useState } from 'react';
import { type AnnouncementRow } from './index';

interface TargetApp {
    id: number;
    name: string;
    slug: string;
    url: string;
    kind: 'login' | 'web';
}

/** ISO del servidor → valor de <input type="datetime-local"> en hora local. */
function toLocalInput(value: string | null): string {
    if (!value) return '';
    const d = new Date(value);
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export default function AnnouncementForm({ announcement, applications }: { announcement: AnnouncementRow | null; applications: TargetApp[] }) {
    const editing = announcement !== null;
    const { data, setData, post, processing, errors, progress } = useForm<{
        title: string;
        image: File | null;
        link_url: string;
        link_label: string;
        starts_at: string;
        ends_at: string;
        is_active: boolean;
        priority: number;
        application_ids: number[];
        _method?: string;
    }>({
        title: announcement?.title ?? '',
        image: null,
        link_url: announcement?.link_url ?? '',
        link_label: announcement?.link_label ?? '',
        starts_at: toLocalInput(announcement?.starts_at ?? null),
        ends_at: toLocalInput(announcement?.ends_at ?? null),
        is_active: announcement?.is_active ?? true,
        priority: announcement?.priority ?? 0,
        application_ids: announcement?.applications.map((a) => a.id) ?? applications.map((a) => a.id),
        ...(editing ? { _method: 'put' } : {}),
    });

    const [preview, setPreview] = useState<string | null>(announcement?.image_url ?? null);

    useEffect(() => {
        if (!data.image) return;
        const url = URL.createObjectURL(data.image);
        setPreview(url);

        return () => URL.revokeObjectURL(url);
    }, [data.image]);

    const loginApps = useMemo(() => applications.filter((a) => a.kind === 'login'), [applications]);
    const webApps = useMemo(() => applications.filter((a) => a.kind === 'web'), [applications]);

    const toggleApp = (id: number) =>
        setData('application_ids', data.application_ids.includes(id) ? data.application_ids.filter((x) => x !== id) : [...data.application_ids, id]);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(editing ? `/announcements/${announcement.id}` : '/announcements', { forceFormData: true });
    };

    const AppOption = ({ app }: { app: TargetApp }) => {
        const checked = data.application_ids.includes(app.id);

        return (
            <button
                type="button"
                onClick={() => toggleApp(app.id)}
                aria-pressed={checked}
                className={cn('flex items-center gap-3 rounded-lg border p-3 text-left transition', checked ? 'border-primary bg-primary/5' : 'hover:bg-accent/50')}
            >
                <span className={cn('flex size-4 shrink-0 items-center justify-center rounded border', checked && 'border-primary bg-primary text-primary-foreground')}>
                    {checked && <Check className="size-3" />}
                </span>
                <span className="min-w-0">
                    <span className="block truncate text-sm font-medium">{app.name}</span>
                    <span className="text-muted-foreground block truncate text-xs">{app.url.replace(/^https?:\/\//, '')}</span>
                </span>
            </button>
        );
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Anuncios', href: '/announcements' },
                { title: editing ? 'Editar' : 'Nuevo', href: '#' },
            ]}
        >
            <Head title={editing ? `Editar anuncio` : 'Nuevo anuncio'} />
            <form onSubmit={submit} className="grid gap-6 p-4 md:p-6 xl:grid-cols-[minmax(0,1fr)_420px]">
                <div className="space-y-6">
                    <PageHeader
                        title={editing ? 'Editar anuncio' : 'Nuevo anuncio'}
                        description="Se mostrará en una ventana sobre la app justo después de que el usuario inicie sesión."
                    />

                    <Panel title="Imagen del anuncio" description="JPG, PNG, WebP o GIF de hasta 5 MB. Recomendado: horizontal, 1600 × 900 px o similar.">
                        <label
                            className={cn(
                                'hover:border-primary/60 flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed p-6 text-center transition',
                                errors.image && 'border-destructive',
                            )}
                        >
                            <ImageUp className="text-muted-foreground size-8" />
                            <span className="text-sm font-medium">{data.image ? data.image.name : editing ? 'Reemplazar imagen (opcional)' : 'Elegir imagen'}</span>
                            <span className="text-muted-foreground text-xs">Haz clic para buscar el archivo</span>
                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/webp,image/gif"
                                className="sr-only"
                                onChange={(e) => setData('image', e.target.files?.[0] ?? null)}
                            />
                        </label>
                        {progress && <progress value={progress.percentage} max="100" className="mt-2 w-full" />}
                        {errors.image && <p className="text-destructive mt-2 text-xs">{errors.image}</p>}
                    </Panel>

                    <Panel title="Contenido">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Título" error={errors.title} hint="Se usa como texto alternativo de la imagen." className="sm:col-span-2">
                                <Input value={data.title} onChange={(e) => setData('title', e.target.value)} placeholder="Regreso seguro a casa" required />
                            </Field>
                            <Field label="Enlace (opcional)" error={errors.link_url} hint="Al hacer clic en la imagen o en el botón se abre esta página.">
                                <Input type="url" value={data.link_url} onChange={(e) => setData('link_url', e.target.value)} placeholder="https://…" />
                            </Field>
                            <Field label="Texto del botón" error={errors.link_label}>
                                <Input value={data.link_label} onChange={(e) => setData('link_label', e.target.value)} placeholder="Ver más" disabled={!data.link_url} />
                            </Field>
                        </div>
                    </Panel>

                    <Panel title="¿Dónde se muestra?" description="Apps con login: al iniciar sesión. Web principal: una vez por visita.">
                        <div className="space-y-4">
                            <div>
                                <p className="text-muted-foreground mb-2 flex items-center gap-1.5 text-xs font-medium tracking-wide uppercase">
                                    <LogIn className="size-3.5" /> Al iniciar sesión
                                </p>
                                <div className="grid gap-2 sm:grid-cols-2">
                                    {loginApps.map((app) => (
                                        <AppOption key={app.id} app={app} />
                                    ))}
                                </div>
                            </div>
                            {webApps.length > 0 && (
                                <div>
                                    <p className="text-muted-foreground mb-2 flex items-center gap-1.5 text-xs font-medium tracking-wide uppercase">
                                        <Globe className="size-3.5" /> En la web
                                    </p>
                                    <div className="grid gap-2 sm:grid-cols-2">
                                        {webApps.map((app) => (
                                            <AppOption key={app.id} app={app} />
                                        ))}
                                    </div>
                                </div>
                            )}
                            {errors.application_ids && <p className="text-destructive text-xs">{errors.application_ids}</p>}
                        </div>
                    </Panel>

                    <Panel title="Vigencia">
                        <div className="grid gap-4 sm:grid-cols-3">
                            <Field label="Desde" error={errors.starts_at} hint="Vacío: desde ya.">
                                <Input type="datetime-local" value={data.starts_at} onChange={(e) => setData('starts_at', e.target.value)} />
                            </Field>
                            <Field label="Hasta" error={errors.ends_at} hint="Vacío: sin fecha de fin.">
                                <Input type="datetime-local" value={data.ends_at} onChange={(e) => setData('ends_at', e.target.value)} />
                            </Field>
                            <Field label="Prioridad" error={errors.priority} hint="Si hay varios, primero el mayor.">
                                <Input type="number" min={0} max={100} value={data.priority} onChange={(e) => setData('priority', Number(e.target.value))} />
                            </Field>
                        </div>
                        <label className="mt-4 flex items-center gap-2 text-sm">
                            <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} className="accent-primary size-4" />
                            Anuncio activo
                        </label>
                    </Panel>

                    <div className="flex items-center gap-2">
                        <Button type="submit" disabled={processing}>
                            {processing && <LoaderCircle className="size-4 animate-spin" />}
                            {editing ? 'Guardar cambios' : 'Publicar anuncio'}
                        </Button>
                        <Button asChild variant="ghost">
                            <Link href="/announcements">Cancelar</Link>
                        </Button>
                    </div>
                </div>

                {/* Vista previa: así lo verá el usuario al iniciar sesión */}
                <aside className="xl:sticky xl:top-4 xl:self-start">
                    <p className="text-muted-foreground mb-2 text-xs font-medium tracking-wide uppercase">Vista previa</p>
                    <div className="relative overflow-hidden rounded-xl border bg-[repeating-linear-gradient(45deg,var(--muted)_0_10px,transparent_10px_20px)]">
                        <div className="flex min-h-72 items-center justify-center bg-[rgba(5,10,24,.72)] p-6">
                            {preview ? (
                                <div className="relative flex w-full flex-col items-center gap-3">
                                    <img src={preview} alt={data.title || 'Anuncio'} className="max-h-72 w-auto max-w-full rounded-lg bg-white shadow-2xl" />
                                    <span className="absolute -top-3 -right-3 flex size-7 items-center justify-center rounded-full bg-white text-slate-900 shadow">
                                        <X className="size-4" />
                                    </span>
                                    <div className="flex flex-wrap items-center justify-center gap-2">
                                        {data.link_url && (
                                            <span className="rounded-full border border-white/40 bg-white/15 px-4 py-1.5 text-xs font-semibold text-white">
                                                {data.link_label || 'Ver más'}
                                            </span>
                                        )}
                                        <span className="rounded-full bg-[#e3001b] px-4 py-1.5 text-xs font-semibold text-white">Entendido</span>
                                    </div>
                                </div>
                            ) : (
                                <p className="text-sm text-white/70">Elige una imagen para ver la vista previa</p>
                            )}
                        </div>
                    </div>
                </aside>
            </form>
        </AppLayout>
    );
}
