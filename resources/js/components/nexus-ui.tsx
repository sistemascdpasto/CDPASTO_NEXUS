import { cn } from '@/lib/utils';
import { type Paginated, type SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { CheckCircle2, ChevronLeft, ChevronRight, type LucideIcon, X, XCircle } from 'lucide-react';
import { type ReactNode, useEffect, useState } from 'react';

export function PageHeader({ title, description, actions }: { title: string; description?: ReactNode; actions?: ReactNode }) {
    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div className="min-w-0 space-y-1">
                <h1 className="truncate text-xl font-semibold tracking-tight">{title}</h1>
                {description && <div className="text-muted-foreground text-sm">{description}</div>}
            </div>
            {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
        </div>
    );
}

export function StatCard({
    label,
    value,
    hint,
    icon: Icon,
    tone,
    href,
}: {
    label: string;
    value: ReactNode;
    hint?: ReactNode;
    icon?: LucideIcon;
    tone?: string;
    href?: string;
}) {
    const body = (
        <div className="bg-card flex h-full flex-col gap-1 rounded-xl border p-4 transition-colors hover:border-neutral-300 dark:hover:border-neutral-700">
            <div className="text-muted-foreground flex items-center justify-between text-xs font-medium">
                <span>{label}</span>
                {Icon && <Icon className="size-4" style={tone ? { color: tone } : undefined} aria-hidden />}
            </div>
            <div className="text-2xl font-semibold">{value}</div>
            {hint && <div className="text-muted-foreground text-xs">{hint}</div>}
        </div>
    );

    return href ? (
        <Link href={href} prefetch className="block">
            {body}
        </Link>
    ) : (
        body
    );
}

export function Panel({
    title,
    description,
    actions,
    children,
    className,
}: {
    title?: ReactNode;
    description?: ReactNode;
    actions?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    return (
        <section className={cn('bg-card rounded-xl border', className)}>
            {(title || actions) && (
                <header className="flex flex-wrap items-center justify-between gap-2 border-b px-4 py-3">
                    <div>
                        {title && <h2 className="text-sm font-semibold">{title}</h2>}
                        {description && <p className="text-muted-foreground text-xs">{description}</p>}
                    </div>
                    {actions}
                </header>
            )}
            <div className="p-4">{children}</div>
        </section>
    );
}

export function EmptyState({ icon: Icon, title, children }: { icon?: LucideIcon; title: string; children?: ReactNode }) {
    return (
        <div className="text-muted-foreground flex flex-col items-center justify-center gap-2 py-10 text-center text-sm">
            {Icon && <Icon className="size-8 opacity-50" aria-hidden />}
            <p className="text-foreground font-medium">{title}</p>
            {children && <div className="max-w-md">{children}</div>}
        </div>
    );
}

export function Pagination({ meta }: { meta: Paginated<unknown> }) {
    if (meta.last_page <= 1) {
        return meta.total > 0 ? <p className="text-muted-foreground mt-3 text-xs">{meta.total} registros</p> : null;
    }

    const prev = meta.links[0]?.url;
    const next = meta.links[meta.links.length - 1]?.url;

    return (
        <div className="mt-3 flex items-center justify-between gap-2 text-sm">
            <p className="text-muted-foreground text-xs">
                {meta.from}–{meta.to} de {meta.total}
            </p>
            <div className="flex items-center gap-1">
                <PageLink href={prev} label="Anterior">
                    <ChevronLeft className="size-4" />
                </PageLink>
                <span className="text-muted-foreground px-2 text-xs">
                    Página {meta.current_page} de {meta.last_page}
                </span>
                <PageLink href={next} label="Siguiente">
                    <ChevronRight className="size-4" />
                </PageLink>
            </div>
        </div>
    );
}

function PageLink({ href, label, children }: { href?: string | null; label: string; children: ReactNode }) {
    return href ? (
        <Link href={href} preserveScroll className="hover:bg-accent rounded-md border p-1.5" aria-label={label}>
            {children}
        </Link>
    ) : (
        <span className="rounded-md border p-1.5 opacity-40" aria-hidden>
            {children}
        </span>
    );
}

export function NativeSelect({ className, ...props }: React.SelectHTMLAttributes<HTMLSelectElement>) {
    return (
        <select
            className={cn(
                'border-input bg-background focus-visible:ring-ring h-9 rounded-md border px-2.5 text-sm shadow-xs focus-visible:ring-2 focus-visible:outline-hidden',
                className,
            )}
            {...props}
        />
    );
}

/**
 * Aplica filtros como query string y conserva el estado de la página.
 */
export function applyFilters(url: string, filters: Record<string, string | number | null | undefined>) {
    const query = Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== '' && v !== null && v !== undefined));

    router.get(url, query, { preserveState: true, preserveScroll: true, replace: true });
}

export function FlashMessages() {
    const { flash } = usePage<SharedData>().props;
    const [visible, setVisible] = useState<{ success?: string | null; error?: string | null }>({});

    useEffect(() => {
        setVisible({ success: flash.success, error: flash.error });

        if (flash.success && !flash.error) {
            const timer = setTimeout(() => setVisible({}), 6000);

            return () => clearTimeout(timer);
        }
    }, [flash.success, flash.error]);

    if (!visible.success && !visible.error) return null;

    return (
        <div className="fixed right-4 bottom-4 z-50 flex max-w-sm flex-col gap-2" role="status">
            {visible.success && (
                <div className="bg-card flex items-start gap-2 rounded-lg border p-3 text-sm shadow-lg">
                    <CheckCircle2 className="mt-0.5 size-4 shrink-0" style={{ color: 'var(--status-good)' }} />
                    <span className="flex-1">{visible.success}</span>
                    <button onClick={() => setVisible((v) => ({ ...v, success: null }))} aria-label="Cerrar">
                        <X className="size-4 opacity-60" />
                    </button>
                </div>
            )}
            {visible.error && (
                <div className="bg-card flex items-start gap-2 rounded-lg border p-3 text-sm shadow-lg">
                    <XCircle className="mt-0.5 size-4 shrink-0" style={{ color: 'var(--status-critical)' }} />
                    <span className="flex-1">{visible.error}</span>
                    <button onClick={() => setVisible((v) => ({ ...v, error: null }))} aria-label="Cerrar">
                        <X className="size-4 opacity-60" />
                    </button>
                </div>
            )}
        </div>
    );
}

export function Table({ children, className }: { children: ReactNode; className?: string }) {
    return (
        <div className={cn('overflow-x-auto', className)}>
            <table className="[&_th]:text-muted-foreground w-full text-sm [&_tbody_tr]:border-b [&_tbody_tr:last-child]:border-0 [&_td]:px-3 [&_td]:py-2 [&_th]:px-3 [&_th]:py-2 [&_th]:text-left [&_th]:text-xs [&_th]:font-medium [&_th]:whitespace-nowrap [&_thead_tr]:border-b">
                {children}
            </table>
        </div>
    );
}
