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

const eiNavItems = [
    {
        title: 'Dashboard FE',
        href: '/admin/electronic-invoice/dashboard',
        icon: LayoutDashboard,
        match: (url: string) => url === '/admin/electronic-invoice/dashboard',
    },
    {
        title: 'e-CFs Emitidos',
        href: '/admin/electronic-invoice/issued',
        icon: FileText,
        match: (url: string) => url.startsWith('/admin/electronic-invoice/issued'),
    },
    {
        title: 'e-CFs Recibidos',
        href: '/admin/electronic-invoice/received',
        icon: FileInput,
        match: (url: string) => url.startsWith('/admin/electronic-invoice/received'),
    },
    {
        title: 'Auditoría FE',
        href: '/admin/electronic-invoice/audit',
        icon: ClipboardList,
        match: (url: string) => url.startsWith('/admin/electronic-invoice/audit'),
    },
    {
        title: 'Configuración FE',
        href: '/admin/electronic-invoice/settings',
        icon: Settings,
        match: (url: string) => url === '/admin/electronic-invoice/settings',
    },
];

export function NavElectronicInvoice() {
    const page = usePage<SharedData>();
    const currentUrl = page.url;

    return (
        <SidebarGroup>
            <SidebarGroupLabel>Facturación Electrónica</SidebarGroupLabel>
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
