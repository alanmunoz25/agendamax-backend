import { router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { Business } from '@/types/models';
import { Building2, Pencil, Users, UserCog, Briefcase, Mail, Phone, MapPin } from 'lucide-react';
import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

interface Props {
    business: Business;
}

export default function BusinessShow({ business }: Props) {
    const { t } = useTranslation();

    const breadcrumbs = [
        { title: t('breadcrumbs.dashboard'), href: '/dashboard' },
        { title: t('breadcrumbs.businesses'), href: '/businesses' },
        { title: business.name, href: `/businesses/${business.id}` },
    ];

    const statusStyles: Record<string, string> = {
        active: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        inactive: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-400',
        suspended: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    };

    const getStatusLabel = (status: string) => {
        const map: Record<string, string> = {
            active: t('businesses.status_active'),
            inactive: t('businesses.status_inactive'),
            suspended: t('businesses.status_suspended'),
        };
        return map[status] ?? (status.charAt(0).toUpperCase() + status.slice(1));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={business.name} />
            <div className="space-y-6 p-4">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                            <Building2 className="h-6 w-6 text-primary" />
                        </div>
                        <div>
                            <h1 className="text-3xl font-bold tracking-tight text-foreground">{business.name}</h1>
                            <div className="mt-1 flex items-center gap-2">
                                <span
                                    className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${statusStyles[business.status] || statusStyles.inactive}`}
                                >
                                    {getStatusLabel(business.status)}
                                </span>
                                <span className="text-sm text-muted-foreground">{t('businesses.code_label')} {business.invitation_code}</span>
                            </div>
                        </div>
                    </div>
                    <Button onClick={() => router.visit(`/businesses/${business.id}/edit`)}>
                        <Pencil className="mr-2 h-4 w-4" />
                        {t('common.edit')}
                    </Button>
                </div>

                {/* Stats */}
                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">{t('businesses.stat_users')}</CardTitle>
                            <Users className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{business.users_count ?? 0}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">{t('businesses.stat_employees')}</CardTitle>
                            <UserCog className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{business.employees_count ?? 0}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">{t('businesses.stat_services')}</CardTitle>
                            <Briefcase className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{business.services_count ?? 0}</div>
                        </CardContent>
                    </Card>
                </div>

                {/* Details */}
                <Card>
                    <CardHeader>
                        <CardTitle>{t('businesses.details_card_title')}</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {business.description && (
                            <p className="text-sm text-muted-foreground">{business.description}</p>
                        )}
                        <div className="grid gap-4 sm:grid-cols-2">
                            {business.email && (
                                <div className="flex items-center gap-2 text-sm">
                                    <Mail className="h-4 w-4 text-muted-foreground" />
                                    <span>{business.email}</span>
                                </div>
                            )}
                            {business.phone && (
                                <div className="flex items-center gap-2 text-sm">
                                    <Phone className="h-4 w-4 text-muted-foreground" />
                                    <span>{business.phone}</span>
                                </div>
                            )}
                            {business.address && (
                                <div className="flex items-center gap-2 text-sm sm:col-span-2">
                                    <MapPin className="h-4 w-4 text-muted-foreground" />
                                    <span>{business.address}</span>
                                </div>
                            )}
                        </div>
                        <div className="grid gap-4 border-t pt-4 sm:grid-cols-3">
                            <div>
                                <p className="text-xs font-medium text-muted-foreground">{t('businesses.timezone_label')}</p>
                                <p className="text-sm">{business.timezone}</p>
                            </div>
                            <div>
                                <p className="text-xs font-medium text-muted-foreground">{t('businesses.loyalty_stamps_label')}</p>
                                <p className="text-sm">{business.loyalty_stamps_required}</p>
                            </div>
                            <div>
                                <p className="text-xs font-medium text-muted-foreground">{t('businesses.loyalty_reward_label')}</p>
                                <p className="text-sm">{business.loyalty_reward_description || t('businesses.loyalty_not_set')}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
