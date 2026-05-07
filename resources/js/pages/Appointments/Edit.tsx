import { FormEventHandler, useState, useEffect } from 'react';
import { useForm, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
import InputError from '@/components/input-error';
import { DateTimePicker } from '@/components/date-time-picker';
import type { Appointment, Employee, Service } from '@/types/models';

interface EmployeeWithServices extends Employee {
    services: Service[];
}
import { Save, Briefcase, Clock, User as UserIcon, DollarSign, AlertTriangle } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface AppointmentServiceLine {
    id: number;
    service: {
        id: number;
        name: string;
        price: number | string;
        duration: number;
        category?: string | null;
    };
    employee: {
        id: number;
        name: string;
        photo_url: string | null;
    } | null;
}

interface Props {
    appointment: Appointment;
    employees: EmployeeWithServices[];
    services: Service[];
    statuses: string[];
    appointment_service_lines: AppointmentServiceLine[];
}

export default function EditAppointment({
    appointment,
    employees,
    services,
    statuses,
    appointment_service_lines,
}: Props) {
    const { t } = useTranslation();

    const { data, setData, put, processing, errors, isDirty, transform } =
        useForm({
            service_id: appointment.service_id?.toString() || '',
            employee_id: appointment.employee_id?.toString() || 'none',
            scheduled_at: appointment.scheduled_at || '',
            status: appointment.status || 'pending',
            notes: appointment.notes || '',
        });

    transform((data) => ({
        ...data,
        employee_id: data.employee_id === 'none' ? '' : data.employee_id,
    }));

    // Derive selected employee from form data
    const selectedEmployee = employees.find(
        (e) => e.id.toString() === data.employee_id && data.employee_id !== 'none'
    ) ?? null;

    // Services available for the selected employee (filtered by what they can provide)
    const availableServices: Service[] = selectedEmployee?.services ?? [];

    const [selectedService, setSelectedService] = useState<Service | null>(
        appointment.service || null
    );

    // Update selected service when service_id changes (keep in sync with available services)
    useEffect(() => {
        if (data.service_id && availableServices.length > 0) {
            const service = availableServices.find(
                (s) => s.id.toString() === data.service_id
            );
            setSelectedService(service || null);
        } else {
            setSelectedService(null);
        }
    }, [data.service_id, availableServices]);

    // When employee changes, reset service selection if current service is not available for new employee
    const handleEmployeeChange = (value: string) => {
        setData((prev) => {
            const newEmployee = employees.find((e) => e.id.toString() === value && value !== 'none') ?? null;
            const currentServiceStillValid = newEmployee?.services.some(
                (s) => s.id.toString() === prev.service_id
            ) ?? false;
            return {
                ...prev,
                employee_id: value,
                service_id: currentServiceStillValid ? prev.service_id : '',
            };
        });
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(`/appointments/${appointment.id}`);
    };

    const getStatusLabel = (status: string) => {
        const map: Record<string, string> = {
            pending: t('appointments.status_pending'),
            confirmed: t('appointments.status_confirmed'),
            in_progress: t('appointments.status_in_progress'),
            completed: t('appointments.status_completed'),
            cancelled: t('appointments.status_cancelled'),
        };
        return map[status] ?? status;
    };

    // Group available services by category
    const servicesByCategory = availableServices.reduce(
        (acc, service) => {
            const category = service.category || t('common.uncategorized');
            if (!acc[category]) {
                acc[category] = [];
            }
            acc[category].push(service);
            return acc;
        },
        {} as Record<string, Service[]>
    );

    return (
        <AppLayout
            title={t('appointments.edit_title')}
            breadcrumbs={[
                { label: t('breadcrumbs.dashboard'), href: '/dashboard' },
                { label: t('breadcrumbs.appointments'), href: '/appointments' },
                {
                    label: `${t('appointments.show_title')} #${appointment.id}`,
                    href: `/appointments/${appointment.id}`,
                },
                { label: t('breadcrumbs.edit') },
            ]}
        >
            <div className="mx-auto max-w-2xl space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-3xl font-bold tracking-tight text-foreground">
                        {t('appointments.edit_title')}
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        {t('appointments.edit_subtitle', { client: appointment.client?.name })}
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    {/* Client Information (Read-only) */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <UserIcon className="h-5 w-5 text-muted-foreground" />
                                <CardTitle>{t('appointments.client_section')}</CardTitle>
                            </div>
                            <CardDescription>
                                {t('appointments.client_readonly')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            <div className="rounded-md border border-input bg-muted px-3 py-2">
                                <div className="flex items-center gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-full bg-background">
                                        <UserIcon className="h-5 w-5 text-muted-foreground" />
                                    </div>
                                    <div className="flex flex-col">
                                        <span className="font-medium">
                                            {appointment.client?.name}
                                        </span>
                                        <span className="text-sm text-muted-foreground">
                                            {appointment.client?.email}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Service & Employee */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Briefcase className="h-5 w-5 text-muted-foreground" />
                                <CardTitle>{t('appointments.service_section')}</CardTitle>
                            </div>
                            <CardDescription>
                                {t('appointments.service_section_edit_desc')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {/* Employee first — determines which services are available */}
                            <div className="space-y-2">
                                <Label htmlFor="employee_id">{t('appointments.employee_label')}</Label>
                                <Select
                                    value={data.employee_id}
                                    onValueChange={handleEmployeeChange}
                                >
                                    <SelectTrigger id="employee_id">
                                        <SelectValue placeholder={t('appointments.employee_placeholder')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">
                                            {t('appointments.no_preference')}
                                        </SelectItem>
                                        {employees.map((employee) => (
                                            <SelectItem
                                                key={employee.id}
                                                value={employee.id.toString()}
                                            >
                                                {employee.user?.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.employee_id} />
                            </div>

                            {/* Service — disabled until employee is selected */}
                            <div className="space-y-2">
                                <Label htmlFor="service_id">{t('appointments.service_label')}</Label>
                                <Select
                                    value={data.service_id}
                                    onValueChange={(value) =>
                                        setData('service_id', value)
                                    }
                                    disabled={!selectedEmployee}
                                >
                                    <SelectTrigger id="service_id">
                                        <SelectValue
                                            placeholder={
                                                !selectedEmployee
                                                    ? t('appointments.service_select_employee_first')
                                                    : t('appointments.service_placeholder')
                                            }
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {availableServices.length === 0 ? (
                                            <SelectItem value="__empty__" disabled>
                                                {t('appointments.no_services_for_employee')}
                                            </SelectItem>
                                        ) : (
                                            Object.entries(servicesByCategory).map(
                                                ([category, categoryServices]) => (
                                                    <div key={category}>
                                                        <div className="px-2 py-1.5 text-sm font-semibold text-muted-foreground">
                                                            {category}
                                                        </div>
                                                        {categoryServices.map(
                                                            (service) => (
                                                                <SelectItem
                                                                    key={service.id}
                                                                    value={service.id.toString()}
                                                                >
                                                                    <div className="flex flex-col">
                                                                        <span>
                                                                            {service.name}
                                                                        </span>
                                                                        <span className="text-xs text-muted-foreground">
                                                                            RD${service.price} · {service.duration} min
                                                                        </span>
                                                                    </div>
                                                                </SelectItem>
                                                            )
                                                        )}
                                                    </div>
                                                )
                                            )
                                        )}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.service_id} />
                                {selectedService && (
                                    <div className="rounded-md bg-muted p-3">
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                {t('appointments.duration')}:
                                            </span>
                                            <span className="font-medium text-foreground">
                                                {selectedService.duration} {t('services.minutes')}
                                            </span>
                                        </div>
                                        <div className="mt-1 flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                {t('appointments.price')}:
                                            </span>
                                            <span className="font-medium text-foreground">
                                                RD${selectedService.price}
                                            </span>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Date & Time */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Clock className="h-5 w-5 text-muted-foreground" />
                                <CardTitle>{t('appointments.datetime_status_card_title')}</CardTitle>
                            </div>
                            <CardDescription>
                                {t('appointments.datetime_status_card_desc')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="scheduled_at">
                                    {t('appointments.datetime_label')}
                                </Label>
                                <DateTimePicker
                                    value={data.scheduled_at}
                                    onChange={(value) =>
                                        setData('scheduled_at', value)
                                    }
                                    placeholder={t('appointments.datetime_placeholder')}
                                    minDate={new Date()}
                                />
                                <InputError message={errors.scheduled_at} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="status">{t('common.status')}</Label>
                                <Select
                                    value={data.status}
                                    onValueChange={(value) =>
                                        setData('status', value)
                                    }
                                >
                                    <SelectTrigger id="status">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {statuses.map((status) => (
                                            <SelectItem key={status} value={status}>
                                                {getStatusLabel(status)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.status} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="notes">{t('appointments.notes_label')}</Label>
                                <Textarea
                                    id="notes"
                                    value={data.notes}
                                    onChange={(e) =>
                                        setData('notes', e.target.value)
                                    }
                                    placeholder={t('appointments.notes_placeholder')}
                                    rows={3}
                                />
                                <InputError message={errors.notes} />
                            </div>
                        </CardContent>
                    </Card>

                    {/* Actions */}
                    <div className="flex items-center justify-end gap-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => window.history.back()}
                            disabled={processing}
                        >
                            {t('common.cancel')}
                        </Button>
                        <Button type="submit" disabled={processing || !isDirty}>
                            <Save className="mr-2 h-4 w-4" />
                            {processing ? t('common.saving') : t('common.save_changes')}
                        </Button>
                    </div>
                </form>

                {/* Service Lines — read-only reference, Mejora #3 */}
                {appointment_service_lines.length > 0 && (
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Briefcase className="h-5 w-5 text-muted-foreground" />
                                <CardTitle>
                                    {`${t('appointments.services_card_title')} (${appointment_service_lines.length})`}
                                </CardTitle>
                            </div>
                            <CardDescription>
                                {t('appointments.service_section_edit_desc')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {/* Desktop table */}
                            <div className="hidden sm:block">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>{t('appointments.services_card_col_service')}</TableHead>
                                            <TableHead>{t('appointments.services_card_col_employee')}</TableHead>
                                            <TableHead>{t('appointments.services_card_col_price')}</TableHead>
                                            <TableHead>{t('appointments.services_card_col_duration')}</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {appointment_service_lines.map((line) => (
                                            <TableRow key={line.id}>
                                                <TableCell className="font-medium">
                                                    {line.service.name}
                                                </TableCell>
                                                <TableCell>
                                                    {line.employee ? (
                                                        <span className="flex items-center gap-1.5">
                                                            <span className="inline-block h-2 w-2 rounded-full bg-green-500" />
                                                            {line.employee.name}
                                                        </span>
                                                    ) : (
                                                        <span className="flex items-center gap-1 text-amber-600 dark:text-amber-400">
                                                            <AlertTriangle className="h-4 w-4" />
                                                            <span className="text-sm">
                                                                {t('appointments.no_employee_assigned')}
                                                            </span>
                                                        </span>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <span className="flex items-center gap-1">
                                                        <DollarSign className="h-3.5 w-3.5 text-muted-foreground" />
                                                        RD${Number(line.service.price).toFixed(2)}
                                                    </span>
                                                </TableCell>
                                                <TableCell>
                                                    <span className="flex items-center gap-1">
                                                        <Clock className="h-3.5 w-3.5 text-muted-foreground" />
                                                        {line.service.duration} min
                                                    </span>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>

                            {/* Mobile stack */}
                            <div className="space-y-3 sm:hidden">
                                {appointment_service_lines.map((line) => (
                                    <div
                                        key={line.id}
                                        className="rounded-lg border border-border bg-muted/30 p-3"
                                    >
                                        <p className="font-medium text-foreground">
                                            {line.service.name}
                                        </p>
                                        <div className="mt-2 space-y-1 text-sm">
                                            {line.employee ? (
                                                <p className="flex items-center gap-1.5 text-foreground">
                                                    <span className="inline-block h-2 w-2 rounded-full bg-green-500" />
                                                    {line.employee.name}
                                                </p>
                                            ) : (
                                                <p className="flex items-center gap-1 text-amber-600 dark:text-amber-400">
                                                    <AlertTriangle className="h-4 w-4" />
                                                    {t('appointments.no_employee_assigned')}
                                                </p>
                                            )}
                                            <p className="flex items-center gap-1 text-muted-foreground">
                                                <DollarSign className="h-3.5 w-3.5" />
                                                RD${Number(line.service.price).toFixed(2)}
                                                <Clock className="ml-2 h-3.5 w-3.5" />
                                                {line.service.duration} min
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
