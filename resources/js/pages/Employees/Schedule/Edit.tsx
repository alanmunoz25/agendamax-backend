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

interface Props {
    employee: Employee;
    schedules: EmployeeSchedule[];
}

export default function EditEmployeeSchedule({ employee, schedules }: Props) {
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
            title="Edit Employee Schedule"
            breadcrumbs={[
                { label: 'Dashboard', href: '/dashboard' },
                { label: 'Employees', href: '/employees' },
                {
                    label: employee.user?.name || 'Employee',
                    href: `/employees/${employee.id}`,
                },
                { label: 'Schedule' },
            ]}
        >
            <div className="mx-auto max-w-4xl space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight text-foreground">
                            Edit Weekly Schedule
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Manage {employee.user?.name || 'employee'}'s weekly availability schedule
                        </p>
                    </div>
                    <Calendar className="h-10 w-10 text-muted-foreground" />
                </div>

                <form onSubmit={submit} className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Weekly Availability</CardTitle>
                            <CardDescription>
                                Set the hours when this employee is available to provide services.
                                Appointments can only be scheduled during these hours.
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
                                        Schedule updated successfully!
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
                            Cancel
                        </Link>
                        <div className="flex gap-2">
                            <Button
                                type="submit"
                                disabled={processing || !isDirty}
                                className="min-w-[150px]"
                            >
                                <Save className="mr-2 h-4 w-4" />
                                {processing ? 'Saving...' : 'Save Schedule'}
                            </Button>
                        </div>
                    </div>
                </form>

                {/* Help Section */}
                <Card className="bg-muted/50">
                    <CardHeader>
                        <CardTitle className="text-base">Tips</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm text-muted-foreground">
                        <ul className="list-disc list-inside space-y-1">
                            <li>Click "Add Hours" to set availability for a specific day</li>
                            <li>Each day can have only one time range</li>
                            <li>End time must be after start time</li>
                            <li>Leave days empty if the employee doesn't work that day</li>
                            <li>
                                Appointments can only be scheduled during these available hours
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
