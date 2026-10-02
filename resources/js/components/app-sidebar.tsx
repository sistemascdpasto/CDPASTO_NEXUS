import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Activity, Bell, Bug, ClipboardList, KeyRound, LayoutGrid, ScrollText, ShieldCheck, Users, UsersRound } from 'lucide-react';
import AppLogo from './app-logo';

export function AppSidebar() {
    const { auth, openAlerts } = usePage<SharedData>().props;

    const monitoring: NavItem[] = [
        { title: 'Resumen', url: '/dashboard', icon: LayoutGrid },
        { title: 'Aplicaciones', url: '/applications', icon: Activity },
        { title: 'Errores', url: '/errors', icon: Bug },
        { title: 'Alertas', url: '/alerts', icon: Bell, badge: openAlerts },
    ];

    const people: NavItem[] = [
        { title: 'Conectados ahora', url: '/sessions', icon: UsersRound },
        { title: 'Inicios de sesión', url: '/logins', icon: KeyRound },
        { title: 'Auditoría', url: '/audit', icon: ScrollText },
    ];

    const admin: NavItem[] = [
        { title: 'Usuarios del panel', url: '/users', icon: Users },
        { title: 'Auditoría del panel', url: '/panel-audit', icon: ShieldCheck },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain title="Monitoreo" items={monitoring} />
                <NavMain title="Usuarios de las apps" items={people} />
                {auth.user.is_superadmin && <NavMain title="Administración" items={admin} />}
            </SidebarContent>

            <SidebarFooter>
                <div className="text-muted-foreground flex items-center gap-2 px-2 text-xs group-data-[collapsible=icon]:hidden">
                    <ClipboardList className="size-3.5" />
                    {auth.user.role_label}
                </div>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
