import AppearanceToggleDropdown from '@/components/appearance-dropdown';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { useCommandPalette } from '@/components/command-palette';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { type BreadcrumbItem as BreadcrumbItemType, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Radio, Search } from 'lucide-react';

export function AppSidebarHeader({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItemType[] }) {
    const { systemPulse } = usePage<SharedData>().props;
    const { open } = useCommandPalette();

    const down = Number(systemPulse?.down ?? 0);
    const degraded = Number(systemPulse?.degraded ?? 0);
    const total = Number(systemPulse?.total ?? 0);
    const tone = down > 0 ? 'var(--status-critical)' : degraded > 0 ? 'var(--status-warning)' : 'var(--status-good)';
    const label =
        down > 0 ? `${down} caída${down > 1 ? 's' : ''}` : degraded > 0 ? `${degraded} degradada${degraded > 1 ? 's' : ''}` : 'Todo operativo';

    return (
        <header className="glass sticky top-0 z-20 flex h-14 shrink-0 items-center gap-3 border-b px-4 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12">
            <SidebarTrigger className="-ml-1" />
            <div className="min-w-0 flex-1">
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>

            <button
                onClick={open}
                className="bg-background/60 text-muted-foreground hover:text-foreground hover:border-primary/40 hidden h-9 w-64 items-center gap-2 rounded-lg border px-3 text-sm transition-colors md:flex"
            >
                <Search className="size-4" />
                <span className="flex-1 text-left">Buscar…</span>
                <kbd className="bg-muted rounded px-1.5 py-0.5 font-mono text-[10px]">Ctrl K</kbd>
            </button>
            <button onClick={open} className="hover:bg-accent rounded-md p-2 md:hidden" aria-label="Buscar">
                <Search className="size-4" />
            </button>

            {total > 0 && (
                <Link
                    href="/noc"
                    className="hover:bg-accent hidden items-center gap-2 rounded-full border px-3 py-1 text-xs font-medium sm:inline-flex"
                    title="Abrir el centro de operaciones"
                >
                    <span className="relative flex size-2">
                        <span className="absolute inline-flex size-full animate-ping rounded-full opacity-60" style={{ background: tone }} />
                        <span className="relative inline-flex size-2 rounded-full" style={{ background: tone }} />
                    </span>
                    {label}
                    <Radio className="text-muted-foreground size-3.5" />
                </Link>
            )}

            <AppearanceToggleDropdown />
        </header>
    );
}
