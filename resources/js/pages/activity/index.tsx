import { EmptyState, NativeSelect, PageHeader, applyFilters } from '@/components/nexus-ui';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime, formatRelative } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type AppRef } from '@/types';
import { Head, Link, usePoll } from '@inertiajs/react';
import { AlertOctagon, Bug, Download, FilePlus2, FileUp, KeyRound, Pencil, ScrollText, ShieldAlert, Trash2 } from 'lucide-react';
import { AnimatePresence, motion } from 'motion/react';

type Kind = 'audit' | 'login' | 'error' | 'alert';

interface FeedItem {
    id: string;
    kind: Kind;
    action: string;
    app: string | null;
    app_id: number | null;
    title: string;
    subtitle: string;
    href: string;
    at: string;
}

interface Props {
    items: FeedItem[];
    applications: AppRef[];
    filters: { application_id: number | null; kinds: Kind[] };
    refreshedAt: string;
}

const kinds: { value: Kind; label: string }[] = [
    { value: 'audit', label: 'Acciones' },
    { value: 'login', label: 'Accesos' },
    { value: 'error', label: 'Errores' },
    { value: 'alert', label: 'Alertas' },
];

function visual(item: FeedItem): { icon: typeof Bug; color: string } {
    if (item.kind === 'error') return { icon: Bug, color: 'var(--status-critical)' };
    if (item.kind === 'alert') return { icon: AlertOctagon, color: item.action === 'critical' ? 'var(--status-critical)' : 'var(--status-warning)' };
    if (item.kind === 'login') {
        return item.action === 'login' ? { icon: KeyRound, color: 'var(--status-good)' } : { icon: ShieldAlert, color: 'var(--status-critical)' };
    }

    return (
        {
            created: { icon: FilePlus2, color: 'var(--status-good)' },
            updated: { icon: Pencil, color: 'var(--series-1)' },
            deleted: { icon: Trash2, color: 'var(--status-critical)' },
            uploaded: { icon: FileUp, color: 'var(--series-2)' },
            downloaded: { icon: Download, color: 'var(--status-warning)' },
        }[item.action] ?? { icon: ScrollText, color: 'var(--status-unknown)' }
    );
}

/** Agrupa por día para los separadores de la línea de tiempo. */
function dayLabel(iso: string): string {
    const d = new Date(iso);
    const today = new Date();
    const yesterday = new Date(today.getFullYear(), today.getMonth(), today.getDate() - 1);

    if (d.toDateString() === today.toDateString()) return 'Hoy';
    if (d.toDateString() === yesterday.toDateString()) return 'Ayer';

    return d.toLocaleDateString('es-CO', { weekday: 'long', day: 'numeric', month: 'long' });
}

export default function ActivityIndex({ items, applications, filters, refreshedAt }: Props) {
    usePoll(10_000, {}, { keepAlive: false });

    const toggleKind = (kind: Kind) => {
        const next = filters.kinds.includes(kind) ? filters.kinds.filter((k) => k !== kind) : [...filters.kinds, kind];
        applyFilters('/activity', {
            application_id: filters.application_id ?? undefined,
            ...(next.length ? Object.fromEntries(next.map((k, i) => [`kinds[${i}]`, k])) : {}),
        });
    };

    let lastDay = '';

    return (
        <AppLayout breadcrumbs={[{ title: 'Actividad en vivo', href: '/activity' }]}>
            <Head title="Actividad en vivo" />
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-4 p-4 sm:p-6">
                <PageHeader
                    title="Actividad en vivo"
                    description={
                        <span className="inline-flex items-center gap-2">
                            <span className="relative flex size-2">
                                <span className="absolute inline-flex size-full animate-ping rounded-full bg-[var(--status-good)] opacity-60" />
                                <span className="relative inline-flex size-2 rounded-full bg-[var(--status-good)]" />
                            </span>
                            Lo que está pasando en todos los sistemas · actualizado {formatRelative(refreshedAt)}
                        </span>
                    }
                />

                <div className="flex flex-wrap items-center gap-2">
                    {kinds.map((k) => {
                        const active = filters.kinds.includes(k.value);

                        return (
                            <button
                                key={k.value}
                                onClick={() => toggleKind(k.value)}
                                className={cn(
                                    'rounded-full border px-3 py-1 text-sm transition-colors',
                                    active ? 'bg-primary text-primary-foreground border-primary' : 'text-muted-foreground hover:bg-accent',
                                )}
                            >
                                {k.label}
                            </button>
                        );
                    })}
                    <NativeSelect
                        className="ml-auto"
                        value={filters.application_id ?? ''}
                        onChange={(e) =>
                            applyFilters('/activity', {
                                application_id: e.target.value,
                                ...Object.fromEntries(filters.kinds.map((k, i) => [`kinds[${i}]`, k])),
                            })
                        }
                        aria-label="Aplicación"
                    >
                        <option value="">Todas las apps</option>
                        {applications.map((a) => (
                            <option key={a.id} value={a.id}>
                                {a.name}
                            </option>
                        ))}
                    </NativeSelect>
                </div>

                {items.length === 0 ? (
                    <EmptyState icon={ScrollText} title="Sin actividad en los últimos dos días" />
                ) : (
                    <ol className="relative">
                        <AnimatePresence initial={false}>
                            {items.map((item) => {
                                const { icon: Icon, color } = visual(item);
                                const day = dayLabel(item.at);
                                const showDay = day !== lastDay;
                                lastDay = day;

                                return (
                                    <motion.li
                                        key={item.id}
                                        layout
                                        initial={{ opacity: 0, y: -12 }}
                                        animate={{ opacity: 1, y: 0 }}
                                        exit={{ opacity: 0 }}
                                        transition={{ type: 'spring', stiffness: 300, damping: 30 }}
                                    >
                                        {showDay && (
                                            <p className="text-muted-foreground mt-4 mb-2 text-xs font-semibold tracking-wider uppercase first:mt-0">
                                                {day}
                                            </p>
                                        )}
                                        <Link
                                            href={item.href}
                                            className="group hover:bg-card flex gap-3 rounded-xl border border-transparent p-3 transition-colors hover:border-[var(--border)]"
                                        >
                                            <span
                                                className="flex size-9 shrink-0 items-center justify-center rounded-lg"
                                                style={{ background: `color-mix(in oklab, ${color} 14%, transparent)` }}
                                            >
                                                <Icon className="size-4" style={{ color }} />
                                            </span>
                                            <div className="min-w-0 flex-1">
                                                <p className="text-sm">
                                                    <span className="font-medium">{item.title}</span>
                                                </p>
                                                <p className="text-muted-foreground truncate text-xs">
                                                    <span className="text-foreground/80 font-medium">{item.app}</span>
                                                    {item.subtitle && ` · ${item.subtitle}`}
                                                </p>
                                            </div>
                                            <time
                                                className="text-muted-foreground shrink-0 text-xs whitespace-nowrap"
                                                title={formatDateTime(item.at)}
                                            >
                                                {formatRelative(item.at)}
                                            </time>
                                        </Link>
                                    </motion.li>
                                );
                            })}
                        </AnimatePresence>
                    </ol>
                )}
            </div>
        </AppLayout>
    );
}
