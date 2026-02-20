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

interface Props {
    employee: Employee;
    schedules: EmployeeSchedule[];
}

const DAYS = [
    'Sunday',
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
];

export default function ViewEmployeeSchedule({ employee, schedules }: Props) {
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
            title="Employee Schedule"
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
                            Weekly Schedule
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Viewing {employee.user?.name || 'employee'}'s weekly availability schedule
                        </p>
                    </div>
                    <Link href={`/employees/${employee.id}/schedules/edit`}>
                        <Button>
                            <Edit className="mr-2 h-4 w-4" />
                            Edit Schedule
                        </Button>
                    </Link>
                </div>

                {/* Schedule Display */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Calendar className="h-5 w-5" />
                            Weekly Availability
                        </CardTitle>
                        <CardDescription>
                            Available hours for booking appointments
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {schedules.length === 0 ? (
                            <div className="text-center py-12">
                                <Clock className="mx-auto h-12 w-12 text-muted-foreground/50 mb-4" />
                                <p className="text-sm text-muted-foreground mb-4">
                                    No availability schedule set for this employee.
                                </p>
                                <Link href={`/employees/${employee.id}/schedules/edit`}>
                                    <Button variant="outline">
                                        <Edit className="mr-2 h-4 w-4" />
                                        Set Schedule
                                    </Button>
                                </Link>
                            </div>
                        ) : (
                            <div className="space-y-2">
                                {DAYS.map((day, index) => {
                                    const schedule = schedulesByDay[index];
                                    return (
                                        <div
                                            key={index}
                                            className="flex items-center justify-between border rounded-lg p-4"
                                        >
                                            <div className="flex items-center gap-4">
                                                <div className="min-w-[120px]">
                                                    <p className="font-medium">{day}</p>
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
                                                        Not available
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
                                                        ? 'Available'
                                                        : 'Unavailable'}
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
                            <CardTitle className="text-base">Summary</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Working Days:</span>
                                <span className="font-medium">
                                    {schedules.length} {schedules.length === 1 ? 'day' : 'days'} per week
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Total Hours:</span>
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
                                    hours per week
                                </span>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
