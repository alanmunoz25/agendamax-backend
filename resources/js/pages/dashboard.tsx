import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem, type SharedData } from '@/types';
import type { Business } from '@/types/models';
import { Head, usePage } from '@inertiajs/react';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Building2, Users, Calendar, Briefcase, UserCog } from 'lucide-react';

interface SuperAdminStats {
    total_businesses: number;
    total_users: number;
    active_businesses: number;
    recent_businesses: Pick<Business, 'id' | 'name' | 'status' | 'created_at'>[];
}

interface BusinessAdminStats {
    today_appointments: number;
    total_clients: number;
    active_employees: number;
    total_services: number;
}

interface Props {
    stats: SuperAdminStats | BusinessAdminStats;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

function isSuperAdminStats(stats: SuperAdminStats | BusinessAdminStats): stats is SuperAdminStats {
    return 'total_businesses' in stats;
}

function SuperAdminDashboard({ stats }: { stats: SuperAdminStats }) {
    return (
        <div className="space-y-6">
            <div className="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">Total Businesses</CardTitle>
                        <Building2 className="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">{stats.total_businesses}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">Active Businesses</CardTitle>
                        <Building2 className="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">{stats.active_businesses}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">Total Users</CardTitle>
                        <Users className="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">{stats.total_users}</div>
                    </CardContent>
                </Card>
            </div>

            {stats.recent_businesses.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle>Recent Businesses</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            {stats.recent_businesses.map((business) => (
                                <div key={business.id} className="flex items-center justify-between border-b pb-3 last:border-0 last:pb-0">
                                    <div>
                                        <p className="font-medium text-foreground">{business.name}</p>
                                        <p className="text-xs text-muted-foreground">
                                            Created {new Date(business.created_at).toLocaleDateString()}
                                        </p>
                                    </div>
                                    <span
                                        className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                            business.status === 'active'
                                                ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                                : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-400'
                                        }`}
                                    >
                                        {business.status}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}

function BusinessAdminDashboard({ stats }: { stats: BusinessAdminStats }) {
    return (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">Today's Appointments</CardTitle>
                    <Calendar className="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div className="text-2xl font-bold">{stats.today_appointments}</div>
                </CardContent>
            </Card>
            <Card>
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">Total Clients</CardTitle>
                    <Users className="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div className="text-2xl font-bold">{stats.total_clients}</div>
                </CardContent>
            </Card>
            <Card>
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">Active Employees</CardTitle>
                    <UserCog className="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div className="text-2xl font-bold">{stats.active_employees}</div>
                </CardContent>
            </Card>
            <Card>
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">Total Services</CardTitle>
                    <Briefcase className="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div className="text-2xl font-bold">{stats.total_services}</div>
                </CardContent>
            </Card>
        </div>
    );
}

export default function Dashboard({ stats }: Props) {
    const { permissions } = usePage<SharedData>().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight text-foreground">Dashboard</h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        {permissions.is_super_admin
                            ? 'Platform overview and management'
                            : 'Your business at a glance'}
                    </p>
                </div>

                {isSuperAdminStats(stats) ? (
                    <SuperAdminDashboard stats={stats} />
                ) : (
                    <BusinessAdminDashboard stats={stats} />
                )}
            </div>
        </AppLayout>
    );
}
