import { FormEventHandler } from 'react';
import { useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    AvailabilitySlotPicker,
    ScheduleSlot,
} from '@/components/availability-slot-picker';
import InputError from '@/components/input-error';
import type { Employee, EmployeeSchedule } from '@/types/models';
import { Calendar, Save, X } from 'lucide-react';
import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

interface Props {
    employee: Employee;
    schedules: EmployeeSchedule[];
}

export default function EditEmployeeSchedule({ employee, schedules }: Props) {
    const { t } = useTranslation();

    // Transform backend schedules to component format
    const initialSchedules: ScheduleSlot[] = schedules.map((schedule) => ({
        day_of_week: schedule.day_of_week,
        start_time: schedule.start_time,
        end_time: schedule.end_time,
        is_available: schedule.is_available,
    }));

    const { data, setData, put, processing, errors, isDirty, recentlySuccessful } = useForm({
        schedules: initialSchedules,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(`/employees/${employee.id}/schedules`);
    };

    const handleScheduleChange = (newSchedules: ScheduleSlot[]) => {
        setData('schedules', newSchedules);
    };

    return (
        <AppLayout
            title={t('schedule.edit_title')}
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
                            {t('schedule.edit_weekly_title')}
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {t('schedule.edit_subtitle', { name: employee.user?.name || t('employees.title') })}
                        </p>
                    </div>
                    <Calendar className="h-10 w-10 text-muted-foreground" />
                </div>

                <form onSubmit={submit} className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('schedule.availability_title')}</CardTitle>
                            <CardDescription>
                                {t('schedule.availability_edit_desc')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div>
                                <AvailabilitySlotPicker
                                    value={data.schedules}
                                    onChange={handleScheduleChange}
                                    disabled={processing}
                                />
                                <InputError message={errors.schedules} className="mt-2" />
                            </div>

                            {recentlySuccessful && (
                                <div className="rounded-md bg-green-50 dark:bg-green-900/20 p-4">
                                    <p className="text-sm text-green-800 dark:text-green-200">
                                        {t('schedule.save_success')}
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Actions */}
                    <div className="flex items-center justify-between gap-4">
                        <Link
                            href={`/employees/${employee.id}`}
                            className="inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:bg-accent hover:text-accent-foreground h-10 px-4 py-2"
                        >
                            <X className="mr-2 h-4 w-4" />
                            {t('common.cancel')}
                        </Link>
                        <div className="flex gap-2">
                            <Button
                                type="submit"
                                disabled={processing || !isDirty}
                                className="min-w-[150px]"
                            >
                                <Save className="mr-2 h-4 w-4" />
                                {processing ? t('common.saving') : t('schedule.save_btn')}
                            </Button>
                        </div>
                    </div>
                </form>

                {/* Help Section */}
                <Card className="bg-muted/50">
                    <CardHeader>
                        <CardTitle className="text-base">{t('schedule.tips_title')}</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm text-muted-foreground">
                        <ul className="list-disc list-inside space-y-1">
                            <li>{t('schedule.tip_1')}</li>
                            <li>{t('schedule.tip_2')}</li>
                            <li>{t('schedule.tip_3')}</li>
                            <li>{t('schedule.tip_4')}</li>
                            <li>{t('schedule.tip_5')}</li>
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
