import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import type { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    LayoutDashboard,
    FileText,
    FileInput,
    Settings,
    ClipboardList,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';

export function NavElectronicInvoice() {
    const page = usePage<SharedData>();
    const currentUrl = page.url;
    const { t } = useTranslation();

    const eiNavItems = [
        {
            title: t('nav_ei.dashboard'),
            href: '/admin/electronic-invoice/dashboard',
            icon: LayoutDashboard,
            match: (url: string) => url === '/admin/electronic-invoice/dashboard',
        },
        {
            title: t('nav_ei.issued'),
            href: '/admin/electronic-invoice/issued',
            icon: FileText,
            match: (url: string) => url.startsWith('/admin/electronic-invoice/issued'),
        },
        {
            title: t('nav_ei.received'),
            href: '/admin/electronic-invoice/received',
            icon: FileInput,
            match: (url: string) => url.startsWith('/admin/electronic-invoice/received'),
        },
        {
            title: t('nav_ei.audit'),
            href: '/admin/electronic-invoice/audit',
            icon: ClipboardList,
            match: (url: string) => url.startsWith('/admin/electronic-invoice/audit'),
        },
        {
            title: t('nav_ei.settings'),
            href: '/admin/electronic-invoice/settings',
            icon: Settings,
            match: (url: string) => url === '/admin/electronic-invoice/settings',
        },
    ];

    return (
        <SidebarGroup>
            <SidebarGroupLabel>{t('nav_ei.title')}</SidebarGroupLabel>
            <SidebarMenu>
                {eiNavItems.map((item) => (
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
