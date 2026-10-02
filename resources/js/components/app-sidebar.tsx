import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    Activity,
    AppWindow,
    Bell,
    Bug,
    CircleDollarSign,
    KeyRound,
    LayoutGrid,
    Radio,
    ScrollText,
    ShieldCheck,
    Siren,
    Users,
    UsersRound,
} from 'lucide-react';
import AppLogo from './app-logo';

export function AppSidebar() {
    const { auth, openAlerts } = usePage<SharedData>().props;

    const monitoring: NavItem[] = [
        { title: 'Resumen', url: '/dashboard', icon: LayoutGrid },
        { title: 'Centro de operaciones', url: '/noc', icon: Radio },
        { title: 'Aplicaciones', url: '/applications', icon: AppWindow },
        { title: 'Incidentes', url: '/incidents', icon: Siren },
        { title: 'Errores', url: '/errors', icon: Bug },
        { title: 'Alertas', url: '/alerts', icon: Bell, badge: openAlerts },
    ];

    const people: NavItem[] = [
        { title: 'Actividad en vivo', url: '/activity', icon: Activity },
        { title: 'Conectados ahora', url: '/sessions', icon: UsersRound },
        { title: 'Inicios de sesión', url: '/logins', icon: KeyRound },
        { title: 'Auditoría', url: '/audit', icon: ScrollText },
    ];

    const admin: NavItem[] = [
        { title: 'Costos de Railway', url: '/costs', icon: CircleDollarSign },
        { title: 'Usuarios del panel', url: '/users', icon: Users },
        { title: 'Auditoría del panel', url: '/panel-audit', icon: ShieldCheck },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild className="hover:bg-sidebar-accent">
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="gap-1">
                <NavMain title="Monitoreo" items={monitoring} />
                <NavMain title="Usuarios de las apps" items={people} />
                {auth.user.is_superadmin && <NavMain title="Administración" items={admin} />}
            </SidebarContent>

            <SidebarFooter>
                <div className="text-sidebar-foreground/60 flex items-center justify-between px-2 text-[11px] group-data-[collapsible=icon]:hidden">
                    <span>{auth.user.role_label}</span>
                    <kbd className="rounded border border-white/10 px-1.5 py-0.5 font-mono">Ctrl K</kbd>
                </div>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
