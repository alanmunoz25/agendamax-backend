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
import { useTranslation } from 'react-i18next';

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

function isSuperAdminStats(stats: SuperAdminStats | BusinessAdminStats): stats is SuperAdminStats {
    return 'total_businesses' in stats;
}

function SuperAdminDashboard({ stats }: { stats: SuperAdminStats }) {
    const { t } = useTranslation();

    return (
        <div className="space-y-6">
            <div className="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">{t('dashboard.total_businesses')}</CardTitle>
                        <Building2 className="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">{stats.total_businesses}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">{t('dashboard.active_businesses')}</CardTitle>
                        <Building2 className="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">{stats.active_businesses}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">{t('dashboard.total_users')}</CardTitle>
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
                        <CardTitle>{t('dashboard.recent_businesses')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            {stats.recent_businesses.map((business) => (
                                <div key={business.id} className="flex items-center justify-between border-b pb-3 last:border-0 last:pb-0">
                                    <div>
                                        <p className="font-medium text-foreground">{business.name}</p>
                                        <p className="text-xs text-muted-foreground">
                                            {t('dashboard.created')} {new Date(business.created_at).toLocaleDateString('es-DO')}
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
    const { t } = useTranslation();

    return (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">{t('dashboard.today_appointments')}</CardTitle>
                    <Calendar className="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div className="text-2xl font-bold">{stats.today_appointments}</div>
                </CardContent>
            </Card>
            <Card>
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">{t('dashboard.total_clients')}</CardTitle>
                    <Users className="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div className="text-2xl font-bold">{stats.total_clients}</div>
                </CardContent>
            </Card>
            <Card>
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">{t('dashboard.active_employees')}</CardTitle>
                    <UserCog className="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div className="text-2xl font-bold">{stats.active_employees}</div>
                </CardContent>
            </Card>
            <Card>
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">{t('dashboard.total_services')}</CardTitle>
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
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('breadcrumbs.dashboard'),
            href: dashboard().url,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('dashboard.title')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight text-foreground">{t('dashboard.title')}</h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        {permissions.is_super_admin
                            ? t('dashboard.subtitle_super')
                            : t('dashboard.subtitle_admin')}
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
