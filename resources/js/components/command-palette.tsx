import { statusColor } from '@/components/status-badge';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { useAppearance } from '@/hooks/use-appearance';
import { type AppStatus, type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { Command } from 'cmdk';
import {
    Activity,
    Bell,
    Bug,
    CircleDollarSign,
    KeyRound,
    LayoutGrid,
    LoaderCircle,
    Monitor,
    Moon,
    Radio,
    ScrollText,
    Search,
    ShieldCheck,
    Siren,
    Sun,
    User,
    Users,
    UsersRound,
} from 'lucide-react';
import { createContext, type ReactNode, useCallback, useContext, useEffect, useRef, useState } from 'react';

interface SearchResults {
    apps: { id: number; name: string; status: AppStatus }[];
    people: { name: string; detail: string | null; app: string | null; href: string }[];
    errors: { title: string; detail: string; href: string }[];
}

const PaletteContext = createContext<{ open: () => void }>({ open: () => {} });

export function useCommandPalette() {
    return useContext(PaletteContext);
}

/**
 * Paleta de comandos global: Ctrl/⌘ + K. Navegación, acciones y búsqueda de apps, personas y errores.
 */
export function CommandPaletteProvider({ children }: { children: ReactNode }) {
    const [isOpen, setIsOpen] = useState(false);

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                setIsOpen((v) => !v);
            }
        };

        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, []);

    return (
        <PaletteContext.Provider value={{ open: () => setIsOpen(true) }}>
            {children}
            <CommandPalette open={isOpen} onOpenChange={setIsOpen} />
        </PaletteContext.Provider>
    );
}

function CommandPalette({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
    const { auth } = usePage<SharedData>().props;
    const { updateAppearance } = useAppearance();
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<SearchResults | null>(null);
    const [loading, setLoading] = useState(false);
    const timer = useRef<ReturnType<typeof setTimeout>>(undefined);

    useEffect(() => {
        clearTimeout(timer.current);

        if (query.trim().length < 2) {
            setResults(null);
            setLoading(false);

            return;
        }

        setLoading(true);
        timer.current = setTimeout(() => {
            fetch(`/search?q=${encodeURIComponent(query.trim())}`, { headers: { Accept: 'application/json' } })
                .then((r) => (r.ok ? r.json() : null))
                .then((data) => setResults(data))
                .finally(() => setLoading(false));
        }, 220);
    }, [query]);

    useEffect(() => {
        if (!open) setQuery('');
    }, [open]);

    const go = useCallback(
        (href: string) => {
            onOpenChange(false);
            router.visit(href);
        },
        [onOpenChange],
    );

    const pages = [
        { label: 'Resumen', href: '/dashboard', icon: LayoutGrid, keywords: 'inicio dashboard' },
        { label: 'Centro de operaciones (modo TV)', href: '/noc', icon: Radio, keywords: 'noc tv pantalla pared' },
        { label: 'Aplicaciones', href: '/applications', icon: Activity, keywords: 'sistemas apps' },
        { label: 'Incidentes', href: '/incidents', icon: Siren, keywords: 'caidas mttr' },
        { label: 'Errores', href: '/errors', icon: Bug, keywords: 'excepciones bugs' },
        { label: 'Alertas', href: '/alerts', icon: Bell, keywords: 'notificaciones' },
        { label: 'Actividad en vivo', href: '/activity', icon: Activity, keywords: 'feed tiempo real' },
        { label: 'Conectados ahora', href: '/sessions', icon: UsersRound, keywords: 'sesiones usuarios activos' },
        { label: 'Inicios de sesión', href: '/logins', icon: KeyRound, keywords: 'login accesos' },
        { label: 'Auditoría', href: '/audit', icon: ScrollText, keywords: 'acciones cambios' },
        ...(auth.user?.is_superadmin
            ? [
                  { label: 'Costos de Railway', href: '/costs', icon: CircleDollarSign, keywords: 'dinero facturacion' },
                  { label: 'Usuarios del panel', href: '/users', icon: Users, keywords: 'jefes encargados' },
                  { label: 'Auditoría del panel', href: '/panel-audit', icon: ShieldCheck, keywords: 'nexus' },
              ]
            : []),
    ];

    const itemClass =
        'flex cursor-pointer items-center gap-3 rounded-md px-3 py-2 text-sm aria-selected:bg-accent aria-selected:text-accent-foreground data-[disabled=true]:opacity-50';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="top-[18%] max-w-xl translate-y-0 gap-0 overflow-hidden p-0 [&>button]:hidden">
                <DialogTitle className="sr-only">Buscar en Nexus</DialogTitle>
                <Command label="Buscar en Nexus" shouldFilter={!results} loop>
                    <div className="flex items-center gap-2 border-b px-3">
                        <Search className="text-muted-foreground size-4 shrink-0" />
                        <Command.Input
                            value={query}
                            onValueChange={setQuery}
                            placeholder="Busca apps, personas, errores o páginas…"
                            className="placeholder:text-muted-foreground h-12 w-full bg-transparent text-sm outline-none"
                        />
                        {loading && <LoaderCircle className="text-muted-foreground size-4 animate-spin" />}
                        <kbd className="text-muted-foreground hidden rounded border px-1.5 text-[10px] sm:inline">ESC</kbd>
                    </div>
                    <Command.List className="max-h-[60vh] overflow-y-auto p-2">
                        <Command.Empty className="text-muted-foreground py-8 text-center text-sm">
                            {loading ? 'Buscando…' : 'Sin resultados.'}
                        </Command.Empty>

                        {results && results.apps.length > 0 && (
                            <Command.Group
                                heading="Aplicaciones"
                                className="text-muted-foreground px-1 text-xs [&_[cmdk-group-heading]]:px-2 [&_[cmdk-group-heading]]:py-1.5"
                            >
                                {results.apps.map((app) => (
                                    <Command.Item
                                        key={`app-${app.id}`}
                                        value={`app ${app.name}`}
                                        onSelect={() => go(`/applications/${app.id}`)}
                                        className={itemClass}
                                    >
                                        <span className="size-2 rounded-full" style={{ background: statusColor(app.status) }} />
                                        <span className="text-foreground">{app.name}</span>
                                    </Command.Item>
                                ))}
                            </Command.Group>
                        )}

                        {results && results.people.length > 0 && (
                            <Command.Group
                                heading="Personas"
                                className="text-muted-foreground px-1 text-xs [&_[cmdk-group-heading]]:px-2 [&_[cmdk-group-heading]]:py-1.5"
                            >
                                {results.people.map((p) => (
                                    <Command.Item
                                        key={p.href}
                                        value={`persona ${p.name} ${p.detail ?? ''} ${p.app ?? ''}`}
                                        onSelect={() => go(p.href)}
                                        className={itemClass}
                                    >
                                        <User className="size-4" />
                                        <span className="text-foreground">{p.name}</span>
                                        <span className="ml-auto truncate text-xs">{p.app}</span>
                                    </Command.Item>
                                ))}
                            </Command.Group>
                        )}

                        {results && results.errors.length > 0 && (
                            <Command.Group
                                heading="Errores"
                                className="text-muted-foreground px-1 text-xs [&_[cmdk-group-heading]]:px-2 [&_[cmdk-group-heading]]:py-1.5"
                            >
                                {results.errors.map((e) => (
                                    <Command.Item
                                        key={e.href}
                                        value={`error ${e.title} ${e.detail}`}
                                        onSelect={() => go(e.href)}
                                        className={itemClass}
                                    >
                                        <Bug className="size-4" />
                                        <span className="text-foreground">{e.title}</span>
                                        <span className="ml-auto truncate text-xs">{e.detail}</span>
                                    </Command.Item>
                                ))}
                            </Command.Group>
                        )}

                        {!results && (
                            <>
                                <Command.Group
                                    heading="Ir a"
                                    className="text-muted-foreground px-1 text-xs [&_[cmdk-group-heading]]:px-2 [&_[cmdk-group-heading]]:py-1.5"
                                >
                                    {pages.map((page) => (
                                        <Command.Item
                                            key={page.href}
                                            value={`${page.label} ${page.keywords}`}
                                            onSelect={() => go(page.href)}
                                            className={itemClass}
                                        >
                                            <page.icon className="size-4" />
                                            <span className="text-foreground">{page.label}</span>
                                        </Command.Item>
                                    ))}
                                </Command.Group>
                                <Command.Group
                                    heading="Tema"
                                    className="text-muted-foreground px-1 text-xs [&_[cmdk-group-heading]]:px-2 [&_[cmdk-group-heading]]:py-1.5"
                                >
                                    {[
                                        { label: 'Tema claro', value: 'light' as const, icon: Sun },
                                        { label: 'Tema oscuro', value: 'dark' as const, icon: Moon },
                                        { label: 'Tema del sistema', value: 'system' as const, icon: Monitor },
                                    ].map((theme) => (
                                        <Command.Item
                                            key={theme.value}
                                            value={theme.label}
                                            onSelect={() => {
                                                updateAppearance(theme.value);
                                                onOpenChange(false);
                                            }}
                                            className={itemClass}
                                        >
                                            <theme.icon className="size-4" />
                                            <span className="text-foreground">{theme.label}</span>
                                        </Command.Item>
                                    ))}
                                </Command.Group>
                            </>
                        )}
                    </Command.List>
                    <div className="text-muted-foreground flex items-center justify-between border-t px-3 py-2 text-[11px]">
                        <span>
                            <kbd className="rounded border px-1">↑</kbd> <kbd className="rounded border px-1">↓</kbd> navegar ·{' '}
                            <kbd className="rounded border px-1">Enter</kbd> abrir
                        </span>
                        <span>Nexus</span>
                    </div>
                </Command>
            </DialogContent>
        </Dialog>
    );
}
