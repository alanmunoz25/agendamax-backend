import { useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { ConfirmationModal } from '@/components/confirmation-modal';
import type { Employee, EmployeeSchedule } from '@/types/models';
import { Edit, Trash2, Users, Briefcase, Mail, User, Calendar, Clock } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { format } from 'date-fns';
import { DAYS_KEYS, formatTime } from '@/components/schedule-utils';

interface Props {
    employee: Employee;
    schedules: EmployeeSchedule[];
}

export default function ShowEmployee({ employee, schedules }: Props) {
    const { t } = useTranslation();
    const [showDeleteModal, setShowDeleteModal] = useState(false);

    const handleDelete = () => {
        router.delete(`/employees/${employee.id}`, {
            onSuccess: () => router.visit('/employees'),
        });
    };

    return (
        <AppLayout
            title={employee.user?.name || t('employees.title')}
            breadcrumbs={[
                { label: t('breadcrumbs.dashboard'), href: '/dashboard' },
                { label: t('breadcrumbs.employees'), href: '/employees' },
                { label: employee.user?.name || t('employees.title') },
            ]}
        >
            <div className="mx-auto max-w-4xl space-y-6">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div className="flex items-center gap-4">
                        {employee.photo_url ? (
                            <img
                                src={employee.photo_url}
                                alt={employee.user?.name}
                                className="h-20 w-20 rounded-full object-cover"
                            />
                        ) : (
                            <div className="flex h-20 w-20 items-center justify-center rounded-full bg-muted">
                                <Users className="h-10 w-10 text-muted-foreground" />
                            </div>
                        )}
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-3xl font-bold tracking-tight text-foreground">
                                    {employee.user?.name}
                                </h1>
                                <Badge
                                    variant={
                                        employee.is_active ? 'success' : 'secondary'
                                    }
                                >
                                    {employee.is_active ? t('employees.status_active') : t('employees.status_inactive')}
                                </Badge>
                            </div>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {employee.user?.email}
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.visit(`/employees/${employee.id}/edit`)
                            }
                        >
                            <Edit className="mr-2 h-4 w-4" />
                            {t('common.edit')}
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => setShowDeleteModal(true)}
                        >
                            <Trash2 className="mr-2 h-4 w-4" />
                            {t('common.delete')}
                        </Button>
                    </div>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    {/* User Information */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <User className="h-5 w-5 text-muted-foreground" />
                                <CardTitle>{t('employees.user_info_card_title')}</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    {t('common.name')}
                                </p>
                                <p className="mt-1 text-base text-foreground">
                                    {employee.user?.name}
                                </p>
                            </div>
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    {t('common.email')}
                                </p>
                                <div className="mt-1 flex items-center gap-2">
                                    <Mail className="h-4 w-4 text-muted-foreground" />
                                    <a
                                        href={`mailto:${employee.user?.email}`}
                                        className="text-base text-primary hover:underline"
                                    >
                                        {employee.user?.email}
                                    </a>
                                </div>
                            </div>
                            {employee.user?.phone && (
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">
                                        {t('common.phone')}
                                    </p>
                                    <p className="mt-1 text-base text-foreground">
                                        {employee.user.phone}
                                    </p>
                                </div>
                            )}
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    {t('common.role')}
                                </p>
                                <p className="mt-1 text-base capitalize text-foreground">
                                    {employee.user?.role?.replace('_', ' ')}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Assigned Services */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Briefcase className="h-5 w-5 text-muted-foreground" />
                                <CardTitle>{t('employees.assigned_services_title')}</CardTitle>
                            </div>
                            <CardDescription>
                                {t('employees.assigned_services_desc')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {employee.services && employee.services.length > 0 ? (
                                <div className="space-y-2">
                                    {employee.services.map((service) => (
                                        <div
                                            key={service.id}
                                            className="flex items-center justify-between rounded-md border border-border bg-muted/50 px-3 py-2"
                                        >
                                            <div>
                                                <p className="font-medium text-foreground">
                                                    {service.name}
                                                </p>
                                                {service.category && (
                                                    <p className="text-sm text-muted-foreground">
                                                        {service.category}
                                                    </p>
                                                )}
                                            </div>
                                            <div className="text-right">
                                                <p className="text-sm font-medium text-foreground">
                                                    RD${service.price}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {service.duration} min
                                                </p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    {t('employees.no_services')}
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    {/* Bio */}
                    {employee.bio && (
                        <Card className="md:col-span-2">
                            <CardHeader>
                                <CardTitle>{t('employees.bio_card_title')}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm text-muted-foreground">
                                    {employee.bio}
                                </p>
                            </CardContent>
                        </Card>
                    )}

                    {/* Weekly Schedules — Mejora #1 */}
                    <Card className="md:col-span-2">
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <Calendar className="h-5 w-5 text-muted-foreground" />
                                    <div>
                                        <CardTitle>{t('employees.schedule.title')}</CardTitle>
                                        <CardDescription className="mt-1">
                                            {t('employees.schedule.desc')}
                                        </CardDescription>
                                    </div>
                                </div>
                                {schedules.length > 0 && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            router.visit(`/employees/${employee.id}/schedules/edit`)
                                        }
                                    >
                                        <Edit className="mr-2 h-4 w-4" />
                                        {t('employees.schedule.edit_btn')}
                                    </Button>
                                )}
                            </div>
                        </CardHeader>
                        <CardContent>
                            {schedules.length === 0 ? (
                                <div className="py-12 text-center">
                                    <Clock className="mx-auto mb-4 h-12 w-12 text-muted-foreground/50" />
                                    <p className="mb-1 font-medium text-foreground">
                                        {t('employees.schedule.empty_title')}
                                    </p>
                                    <p className="mb-4 text-sm text-muted-foreground">
                                        {t('employees.schedule.empty_desc')}
                                    </p>
                                    <Button
                                        variant="outline"
                                        onClick={() =>
                                            router.visit(`/employees/${employee.id}/schedules/edit`)
                                        }
                                    >
                                        <Calendar className="mr-2 h-4 w-4" />
                                        {t('employees.schedule.configure_btn')}
                                    </Button>
                                </div>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>{t('common.date')}</TableHead>
                                            <TableHead>{t('schedule.availability_title').split(' ')[0]}</TableHead>
                                            <TableHead>Fin</TableHead>
                                            <TableHead>{t('common.status')}</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {DAYS_KEYS.map((dayKey, index) => {
                                            const schedule = schedules.find(
                                                (s) => s.day_of_week === index
                                            );
                                            return (
                                                <TableRow key={index}>
                                                    <TableCell className="font-medium">
                                                        {t(dayKey)}
                                                    </TableCell>
                                                    <TableCell>
                                                        {schedule ? formatTime(schedule.start_time) : '—'}
                                                    </TableCell>
                                                    <TableCell>
                                                        {schedule ? formatTime(schedule.end_time) : '—'}
                                                    </TableCell>
                                                    <TableCell>
                                                        {schedule ? (
                                                            <span
                                                                className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${
                                                                    schedule.is_available
                                                                        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                                        : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200'
                                                                }`}
                                                            >
                                                                {schedule.is_available
                                                                    ? t('schedule.available')
                                                                    : t('schedule.unavailable')}
                                                            </span>
                                                        ) : (
                                                            <span className="text-sm text-muted-foreground">—</span>
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>

                    {/* Metadata */}
                    <Card className="md:col-span-2">
                        <CardHeader>
                            <CardTitle>{t('employees.additional_info_title')}</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    {t('common.status')}
                                </p>
                                <p className="mt-1 text-base text-foreground">
                                    <Badge
                                        variant={
                                            employee.is_active
                                                ? 'success'
                                                : 'secondary'
                                        }
                                    >
                                        {employee.is_active ? t('employees.status_active') : t('employees.status_inactive')}
                                    </Badge>
                                </p>
                            </div>
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    {t('common.created_at')}
                                </p>
                                <p className="mt-1 text-base text-foreground">
                                    {format(new Date(employee.created_at), 'dd/MM/yyyy')}
                                </p>
                            </div>
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    {t('common.updated_at')}
                                </p>
                                <p className="mt-1 text-base text-foreground">
                                    {format(new Date(employee.updated_at), 'dd/MM/yyyy')}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            {/* Delete Confirmation Modal */}
            <ConfirmationModal
                open={showDeleteModal}
                onOpenChange={setShowDeleteModal}
                title={t('employees.delete_title')}
                description={
                    <div className="space-y-2">
                        <p>
                            {t('employees.delete_description', { name: employee.user?.name })}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            {t('employees.delete_description_detail')}
                        </p>
                    </div>
                }
                confirmLabel={t('employees.delete_confirm')}
                cancelLabel={t('common.cancel')}
                onConfirm={handleDelete}
                variant="destructive"
            />
        </AppLayout>
    );
}
