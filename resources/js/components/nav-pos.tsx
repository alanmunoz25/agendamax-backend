import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import type { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Banknote, Receipt, ShoppingCart } from 'lucide-react';

const posNavItems = [
    {
        title: 'Cobrar',
        href: '/pos',
        icon: ShoppingCart,
        match: (url: string) => url === '/pos',
    },
    {
        title: 'Tickets',
        href: '/pos/tickets',
        icon: Receipt,
        match: (url: string) => url.startsWith('/pos/tickets'),
    },
    {
        title: 'Cierre',
        href: '/pos/shift-close',
        icon: Banknote,
        match: (url: string) => url.startsWith('/pos/shift-close'),
    },
];

export function NavPos() {
    const page = usePage<SharedData>();
    const currentUrl = page.url;

    return (
        <SidebarGroup>
            <SidebarGroupLabel>Punto de Venta</SidebarGroupLabel>
            <SidebarMenu>
                {posNavItems.map((item) => (
                    <SidebarMenuItem key={item.href}>
                        <SidebarMenuButton asChild isActive={item.match(currentUrl)}>
                            <Link href={item.href} prefetch>
                                <item.icon />
                                <span>{item.title}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}
