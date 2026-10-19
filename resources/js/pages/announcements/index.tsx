import { EmptyState, PageHeader } from '@/components/nexus-ui';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime, formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import { Head, Link, router } from '@inertiajs/react';
import { CalendarClock, Eye, Globe, LogIn, Megaphone, MousePointerClick, Pause, Pencil, Play, Plus, Trash2 } from 'lucide-react';

export interface AnnouncementRow {
    id: number;
    uuid: string;
    title: string;
    image_url: string;
    image_width: number | null;
    image_height: number | null;
    link_url: string | null;
    link_label: string | null;
    starts_at: string | null;
    ends_at: string | null;
    is_active: boolean;
    priority: number;
    state: 'live' | 'scheduled' | 'expired' | 'paused';
    applications: { id: number; name: string; slug: string }[];
    views_total?: number;
    clicks_total?: number;
    views_30d?: number;
}

const stateInfo: Record<AnnouncementRow['state'], { label: string; color: string }> = {
    live: { label: 'Publicado', color: 'var(--status-good)' },
    scheduled: { label: 'Programado', color: 'var(--status-warning)' },
    expired: { label: 'Vencido', color: 'var(--status-unknown)' },
    paused: { label: 'Pausado', color: 'var(--status-unknown)' },
};

function vigencia(a: AnnouncementRow): string {
    if (!a.starts_at && !a.ends_at) return 'Sin fecha de fin';
    if (a.starts_at && a.ends_at) return `${formatDateTime(a.starts_at)} → ${formatDateTime(a.ends_at)}`;
    if (a.starts_at) return `Desde ${formatDateTime(a.starts_at)}`;

    return `Hasta ${formatDateTime(a.ends_at)}`;
}

export default function AnnouncementsIndex({ announcements }: { announcements: AnnouncementRow[] }) {
    return (
        <AppLayout breadcrumbs={[{ title: 'Anuncios', href: '/announcements' }]}>
            <Head title="Anuncios" />
            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title="Anuncios"
                    description={
                        <>
                            Banners que ven los usuarios <strong className="text-foreground font-medium">apenas inician sesión</strong> en Adenar, EasyOL,
                            Tickets y 5S, y los visitantes de la web principal. Las apps los reciben en menos de un minuto.
                        </>
                    }
                    actions={
                        <Button asChild>
                            <Link href="/announcements/create">
                                <Plus className="size-4" /> Nuevo anuncio
                            </Link>
                        </Button>
                    }
                />

                {announcements.length === 0 ? (
                    <div className="bg-card rounded-xl border">
                        <EmptyState icon={Megaphone} title="Aún no hay anuncios">
                            Crea el primero, por ejemplo la campaña de Regreso Seguro a Casa.
                        </EmptyState>
                    </div>
                ) : (
                    <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        {announcements.map((a) => {
                            const state = stateInfo[a.state];

                            return (
                                <article key={a.id} className={cn('bg-card flex flex-col overflow-hidden rounded-xl border', a.state !== 'live' && 'opacity-80')}>
                                    <div className="bg-muted/40 relative flex aspect-[16/9] items-center justify-center overflow-hidden border-b">
                                        <img src={a.image_url} alt={a.title} className="max-h-full max-w-full object-contain" loading="lazy" />
                                        <span className="bg-background/90 absolute top-2 left-2 flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium shadow-sm">
                                            <span className="size-2 rounded-full" style={{ backgroundColor: state.color }} />
                                            {state.label}
                                        </span>
                                        {a.priority > 0 && (
                                            <span className="bg-background/90 absolute top-2 right-2 rounded-full px-2 py-1 text-[11px] shadow-sm">Prioridad {a.priority}</span>
                                        )}
                                    </div>

                                    <div className="flex flex-1 flex-col gap-3 p-4">
                                        <div>
                                            <h2 className="leading-snug font-semibold">{a.title}</h2>
                                            <p className="text-muted-foreground mt-1 flex items-center gap-1.5 text-xs">
                                                <CalendarClock className="size-3.5" /> {vigencia(a)}
                                            </p>
                                        </div>

                                        <div className="flex flex-wrap gap-1.5">
                                            {a.applications.map((app) => (
                                                <span key={app.id} className="bg-accent text-accent-foreground flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px]">
                                                    {app.slug === 'bavaria-cdpasto' ? <Globe className="size-3" /> : <LogIn className="size-3" />}
                                                    {app.name}
                                                </span>
                                            ))}
                                        </div>

                                        <dl className="text-muted-foreground mt-auto grid grid-cols-2 gap-2 border-t pt-3 text-xs">
                                            <div className="flex items-center gap-1.5" title="Veces que se mostró (total)">
                                                <Eye className="size-3.5" />
                                                <dd>
                                                    <span className="text-foreground font-semibold tabular-nums">{formatNumber(a.views_total)}</span> vistas
                                                    <span className="block text-[11px]">{formatNumber(a.views_30d)} en 30 días</span>
                                                </dd>
                                            </div>
                                            <div className="flex items-center gap-1.5" title="Clics en el enlace del anuncio">
                                                <MousePointerClick className="size-3.5" />
                                                <dd>
                                                    <span className="text-foreground font-semibold tabular-nums">{a.link_url ? formatNumber(a.clicks_total) : '—'}</span> clics
                                                    <span className="block text-[11px]">{a.link_url ? 'en el enlace' : 'sin enlace'}</span>
                                                </dd>
                                            </div>
                                        </dl>

                                        <div className="flex items-center gap-1.5">
                                            <Button asChild size="sm" variant="outline">
                                                <Link href={`/announcements/${a.id}/edit`}>
                                                    <Pencil className="size-3.5" /> Editar
                                                </Link>
                                            </Button>
                                            <Button size="sm" variant="ghost" onClick={() => router.post(`/announcements/${a.id}/toggle`, {}, { preserveScroll: true })}>
                                                {a.is_active ? <Pause className="size-3.5" /> : <Play className="size-3.5" />}
                                                {a.is_active ? 'Pausar' : 'Activar'}
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                className="text-destructive ml-auto"
                                                aria-label="Eliminar anuncio"
                                                onClick={() => confirm(`¿Eliminar el anuncio «${a.title}»?`) && router.delete(`/announcements/${a.id}`)}
                                            >
                                                <Trash2 className="size-3.5" />
                                            </Button>
                                        </div>
                                    </div>
                                </article>
                            );
                        })}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
