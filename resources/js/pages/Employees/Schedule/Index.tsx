import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { Employee, EmployeeSchedule } from '@/types/models';
import { Calendar, Edit, Clock } from 'lucide-react';
import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

interface Props {
    employee: Employee;
    schedules: EmployeeSchedule[];
}

const DAYS_KEYS = [
    'schedule.sunday',
    'schedule.monday',
    'schedule.tuesday',
    'schedule.wednesday',
    'schedule.thursday',
    'schedule.friday',
    'schedule.saturday',
];

export default function ViewEmployeeSchedule({ employee, schedules }: Props) {
    const { t } = useTranslation();

    // Group schedules by day
    const schedulesByDay = schedules.reduce(
        (acc, schedule) => {
            acc[schedule.day_of_week] = schedule;
            return acc;
        },
        {} as Record<number, EmployeeSchedule>
    );

    const formatTime = (time: string) => {
        const [hours, minutes] = time.split(':');
        const hour = parseInt(hours, 10);
        const ampm = hour >= 12 ? 'PM' : 'AM';
        const displayHour = hour % 12 || 12;
        return `${displayHour}:${minutes} ${ampm}`;
    };

    return (
        <AppLayout
            title={t('schedule.title')}
            breadcrumbs={[
                { label: t('breadcrumbs.dashboard'), href: '/dashboard' },
                { label: t('breadcrumbs.employees'), href: '/employees' },
                {
                    label: employee.user?.name || t('employees.title'),
                    href: `/employees/${employee.id}`,
                },
                { label: t('breadcrumbs.schedule') },
            ]}
        >
            <div className="mx-auto max-w-4xl space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight text-foreground">
                            {t('schedule.weekly_title')}
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {t('schedule.viewing_subtitle', { name: employee.user?.name || t('employees.title') })}
                        </p>
                    </div>
                    <Link href={`/employees/${employee.id}/schedules/edit`}>
                        <Button>
                            <Edit className="mr-2 h-4 w-4" />
                            {t('schedule.edit_btn')}
                        </Button>
                    </Link>
                </div>

                {/* Schedule Display */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Calendar className="h-5 w-5" />
                            {t('schedule.availability_title')}
                        </CardTitle>
                        <CardDescription>
                            {t('schedule.availability_desc')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {schedules.length === 0 ? (
                            <div className="text-center py-12">
                                <Clock className="mx-auto h-12 w-12 text-muted-foreground/50 mb-4" />
                                <p className="text-sm text-muted-foreground mb-4">
                                    {t('schedule.no_schedule')}
                                </p>
                                <Link href={`/employees/${employee.id}/schedules/edit`}>
                                    <Button variant="outline">
                                        <Edit className="mr-2 h-4 w-4" />
                                        {t('schedule.set_btn')}
                                    </Button>
                                </Link>
                            </div>
                        ) : (
                            <div className="space-y-2">
                                {DAYS_KEYS.map((dayKey, index) => {
                                    const schedule = schedulesByDay[index];
                                    return (
                                        <div
                                            key={index}
                                            className="flex items-center justify-between border rounded-lg p-4"
                                        >
                                            <div className="flex items-center gap-4">
                                                <div className="min-w-[120px]">
                                                    <p className="font-medium">{t(dayKey)}</p>
                                                </div>
                                                {schedule ? (
                                                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                                        <Clock className="h-4 w-4" />
                                                        <span>
                                                            {formatTime(schedule.start_time)} -{' '}
                                                            {formatTime(schedule.end_time)}
                                                        </span>
                                                    </div>
                                                ) : (
                                                    <span className="text-sm text-muted-foreground italic">
                                                        {t('schedule.not_available')}
                                                    </span>
                                                )}
                                            </div>
                                            {schedule && (
                                                <div
                                                    className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${
                                                        schedule.is_available
                                                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                            : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200'
                                                    }`}
                                                >
                                                    {schedule.is_available
                                                        ? t('schedule.available')
                                                        : t('schedule.unavailable')}
                                                </div>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Summary Card */}
                {schedules.length > 0 && (
                    <Card className="bg-muted/50">
                        <CardHeader>
                            <CardTitle className="text-base">{t('schedule.summary_title')}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">{t('schedule.working_days')}</span>
                                <span className="font-medium">
                                    {schedules.length} {schedules.length === 1 ? t('schedule.day') : t('schedule.days')} {t('schedule.per_week')}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">{t('schedule.total_hours')}</span>
                                <span className="font-medium">
                                    {schedules
                                        .reduce((total, schedule) => {
                                            const start = new Date(
                                                `2000-01-01T${schedule.start_time}`
                                            );
                                            const end = new Date(`2000-01-01T${schedule.end_time}`);
                                            const hours =
                                                (end.getTime() - start.getTime()) / (1000 * 60 * 60);
                                            return total + hours;
                                        }, 0)
                                        .toFixed(1)}{' '}
                                    {t('schedule.hours_per_week')}
                                </span>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
