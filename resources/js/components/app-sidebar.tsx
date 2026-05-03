import { NavElectronicInvoice } from '@/components/electronic-invoice/nav-electronic-invoice';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavPayroll } from '@/components/nav-payroll';
import { NavPos } from '@/components/nav-pos';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    Calendar,
    Folder,
    LayoutGrid,
    Briefcase,
    FolderTree,
    GraduationCap,
    Users,
    UserCog,
    Building2,
    Megaphone,
    QrCode as QrCodeIcon,
    Shield,
} from 'lucide-react';
import AppLogo from './app-logo';
import { useMemo } from 'react';

function useNavItems(): NavItem[] {
    const { permissions } = usePage<SharedData>().props;

    return useMemo(() => {
        const items: NavItem[] = [
            {
                title: 'Dashboard',
                href: dashboard(),
                icon: LayoutGrid,
            },
        ];

        if (permissions.can_manage_businesses) {
            items.push({
                title: 'Businesses',
                href: '/businesses',
                icon: Building2,
            });
        }

        if (permissions.can_manage_users) {
            items.push({
                title: 'Users',
                href: '/users',
                icon: Shield,
            });
        }

        // Appointments: visible to business_admin, employee, and client
        if (permissions.is_business_admin || permissions.is_employee || permissions.is_client) {
            items.push({
                title: 'Appointments',
                href: '/appointments',
                icon: Calendar,
            });
        }

        // Clients: visible to business_admin and employee
        if (permissions.is_business_admin || permissions.is_employee) {
            items.push({
                title: 'Clients',
                href: '/clients',
                icon: Users,
            });
        }

        // Admin-only items: employees, services, business, QR codes
        if (permissions.is_business_admin) {
            items.push(
                {
                    title: 'Employees',
                    href: '/employees',
                    icon: UserCog,
                },
                {
                    title: 'Services',
                    href: '/services',
                    icon: Briefcase,
                },
                {
                    title: 'Cursos',
                    href: '/courses',
                    icon: GraduationCap,
                },
                {
                    title: 'Categories',
                    href: '/service-categories',
                    icon: FolderTree,
                },
                {
                    title: 'Promotions',
                    href: '/promotions',
                    icon: Megaphone,
                },
                {
                    title: 'Business',
                    href: '/business',
                    icon: Building2,
                },
                {
                    title: 'QR Codes',
                    href: '/qr-codes',
                    icon: QrCodeIcon,
                },
            );
        }

        return items;
    }, [permissions]);
}

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const navItems = useNavItems();
    const { permissions } = usePage<SharedData>().props;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={navItems} />
                {permissions.is_business_admin && <NavPayroll />}
                {permissions.is_business_admin && <NavElectronicInvoice />}
                {(permissions.is_business_admin || permissions.is_employee) && <NavPos />}
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
