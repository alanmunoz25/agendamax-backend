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
import { useTranslation } from 'react-i18next';

export function NavPos() {
    const page = usePage<SharedData>();
    const currentUrl = page.url;
    const { t } = useTranslation();

    const posNavItems = [
        {
            title: t('nav_pos.charge'),
            href: '/pos',
            icon: ShoppingCart,
            match: (url: string) => url === '/pos',
        },
        {
            title: t('nav_pos.tickets'),
            href: '/pos/tickets',
            icon: Receipt,
            match: (url: string) => url.startsWith('/pos/tickets'),
        },
        {
            title: t('nav_pos.shift_close'),
            href: '/pos/shift-close',
            icon: Banknote,
            match: (url: string) => url.startsWith('/pos/shift-close'),
        },
    ];

    return (
        <SidebarGroup>
            <SidebarGroupLabel>{t('nav_pos.title')}</SidebarGroupLabel>
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
