import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import type { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { BarChart2, CalendarDays, Percent, SlidersHorizontal, TrendingUp } from 'lucide-react';

const payrollNavItems = [
    {
        title: 'Dashboard',
        href: '/payroll/dashboard',
        icon: TrendingUp,
        match: (url: string) => url === '/payroll/dashboard',
    },
    {
        title: 'Períodos',
        href: '/payroll/periods',
        icon: CalendarDays,
        match: (url: string) => url.startsWith('/payroll/periods'),
    },
    {
        title: 'Reportes',
        href: '/payroll/reports/by-service',
        icon: BarChart2,
        match: (url: string) => url.startsWith('/payroll/reports'),
    },
    {
        title: 'Ajustes',
        href: '/payroll/adjustments',
        icon: SlidersHorizontal,
        match: (url: string) => url.startsWith('/payroll/adjustments'),
    },
    {
        title: 'Reglas',
        href: '/payroll/commission-rules',
        icon: Percent,
        match: (url: string) => url.startsWith('/payroll/commission-rules'),
    },
];

export function NavPayroll() {
    const page = usePage<SharedData>();
    const currentUrl = page.url;

    return (
        <SidebarGroup>
            <SidebarGroupLabel>Nómina</SidebarGroupLabel>
            <SidebarMenu>
                {payrollNavItems.map((item) => (
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
