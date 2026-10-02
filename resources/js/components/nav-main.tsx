import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuBadge, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';

export function NavMain({ title, items = [] }: { title?: string; items: NavItem[] }) {
    const page = usePage();
    const path = page.url.split('?')[0];

    return (
        <SidebarGroup className="px-2 py-0">
            {title && <SidebarGroupLabel className="text-sidebar-foreground/50 text-[10px] tracking-wider uppercase">{title}</SidebarGroupLabel>}
            <SidebarMenu>
                {items.map((item) => (
                    <SidebarMenuItem key={item.title}>
                        <SidebarMenuButton
                            asChild
                            isActive={path === item.url || path.startsWith(`${item.url}/`)}
                            tooltip={{ children: item.title }}
                            className="data-[active=true]:before:bg-sidebar-primary relative data-[active=true]:font-medium data-[active=true]:text-white data-[active=true]:before:absolute data-[active=true]:before:top-1.5 data-[active=true]:before:bottom-1.5 data-[active=true]:before:-left-2 data-[active=true]:before:w-1 data-[active=true]:before:rounded-r-full"
                        >
                            <Link href={item.url} prefetch>
                                {item.icon && <item.icon />}
                                <span>{item.title}</span>
                            </Link>
                        </SidebarMenuButton>
                        {!!item.badge && (
                            <SidebarMenuBadge className="bg-destructive/15 text-destructive rounded-full">{item.badge}</SidebarMenuBadge>
                        )}
                    </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}
