import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import type { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { BarChart2, CalendarDays, Percent, SlidersHorizontal, TrendingUp } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export function NavPayroll() {
    const page = usePage<SharedData>();
    const currentUrl = page.url;
    const { t } = useTranslation();

    const payrollNavItems = [
        {
            title: t('nav_payroll.dashboard'),
            href: '/payroll/dashboard',
            icon: TrendingUp,
            match: (url: string) => url === '/payroll/dashboard',
        },
        {
            title: t('nav_payroll.periods'),
            href: '/payroll/periods',
            icon: CalendarDays,
            match: (url: string) => url.startsWith('/payroll/periods'),
        },
        {
            title: t('nav_payroll.reports'),
            href: '/payroll/reports/by-service',
            icon: BarChart2,
            match: (url: string) => url.startsWith('/payroll/reports'),
        },
        {
            title: t('nav_payroll.adjustments'),
            href: '/payroll/adjustments',
            icon: SlidersHorizontal,
            match: (url: string) => url.startsWith('/payroll/adjustments'),
        },
        {
            title: t('nav_payroll.rules'),
            href: '/payroll/commission-rules',
            icon: Percent,
            match: (url: string) => url.startsWith('/payroll/commission-rules'),
        },
    ];

    return (
        <SidebarGroup>
            <SidebarGroupLabel>{t('nav_payroll.title')}</SidebarGroupLabel>
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
