import { FormEventHandler, useState, useEffect } from 'react';
import { useForm } from '@inertiajs/react';
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
import InputError from '@/components/input-error';
import { DateTimePicker } from '@/components/date-time-picker';
import type { Appointment, Employee, Service } from '@/types/models';
import { Save, Briefcase, Clock, User as UserIcon } from 'lucide-react';

interface Props {
    appointment: Appointment;
    employees: Employee[];
    services: Service[];
    statuses: string[];
}

export default function EditAppointment({
    appointment,
    employees,
    services,
    statuses,
}: Props) {
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

    const [selectedService, setSelectedService] = useState<Service | null>(
        appointment.service || null
    );

    // Update selected service when service_id changes
    useEffect(() => {
        if (data.service_id) {
            const service = services.find(
                (s) => s.id.toString() === data.service_id
            );
            setSelectedService(service || null);
        } else {
            setSelectedService(null);
        }
    }, [data.service_id, services]);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(`/appointments/${appointment.id}`);
    };

    // Group services by category
    const servicesByCategory = services.reduce(
        (acc, service) => {
            const category = service.category || 'Uncategorized';
            if (!acc[category]) {
                acc[category] = [];
            }
            acc[category].push(service);
            return acc;
        },
        {} as Record<string, typeof services>
    );

    return (
        <AppLayout
            title="Edit Appointment"
            breadcrumbs={[
                { label: 'Dashboard', href: '/dashboard' },
                { label: 'Appointments', href: '/appointments' },
                {
                    label: `Appointment #${appointment.id}`,
                    href: `/appointments/${appointment.id}`,
                },
                { label: 'Edit' },
            ]}
        >
            <div className="mx-auto max-w-2xl space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-3xl font-bold tracking-tight text-foreground">
                        Edit Appointment
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Update appointment details for {appointment.client?.name}
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    {/* Client Information (Read-only) */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <UserIcon className="h-5 w-5 text-muted-foreground" />
                                <CardTitle>Client Information</CardTitle>
                            </div>
                            <CardDescription>
                                Client cannot be changed after creation
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
                                <CardTitle>Service & Provider</CardTitle>
                            </div>
                            <CardDescription>
                                Update the service and employee
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="service_id">Service</Label>
                                <Select
                                    value={data.service_id}
                                    onValueChange={(value) =>
                                        setData('service_id', value)
                                    }
                                >
                                    <SelectTrigger id="service_id">
                                        <SelectValue placeholder="Select a service" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {Object.entries(servicesByCategory).map(
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
                                                                        {
                                                                            service.name
                                                                        }
                                                                    </span>
                                                                    <span className="text-xs text-muted-foreground">
                                                                        $
                                                                        {
                                                                            service.price
                                                                        }{' '}
                                                                        ·{' '}
                                                                        {
                                                                            service.duration
                                                                        }{' '}
                                                                        min
                                                                    </span>
                                                                </div>
                                                            </SelectItem>
                                                        )
                                                    )}
                                                </div>
                                            )
                                        )}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.service_id} />
                                {selectedService && (
                                    <div className="rounded-md bg-muted p-3">
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                Duration:
                                            </span>
                                            <span className="font-medium text-foreground">
                                                {selectedService.duration} minutes
                                            </span>
                                        </div>
                                        <div className="mt-1 flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                Price:
                                            </span>
                                            <span className="font-medium text-foreground">
                                                ${selectedService.price}
                                            </span>
                                        </div>
                                    </div>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="employee_id">Employee</Label>
                                <Select
                                    value={data.employee_id}
                                    onValueChange={(value) =>
                                        setData('employee_id', value)
                                    }
                                >
                                    <SelectTrigger id="employee_id">
                                        <SelectValue placeholder="Select an employee" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">
                                            Sin preferencia
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
                        </CardContent>
                    </Card>

                    {/* Date & Time */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Clock className="h-5 w-5 text-muted-foreground" />
                                <CardTitle>Date, Time & Status</CardTitle>
                            </div>
                            <CardDescription>
                                Reschedule or update appointment status
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="scheduled_at">
                                    Appointment Date & Time
                                </Label>
                                <DateTimePicker
                                    value={data.scheduled_at}
                                    onChange={(value) =>
                                        setData('scheduled_at', value)
                                    }
                                    placeholder="Pick a date and time"
                                    minDate={new Date()}
                                />
                                <InputError message={errors.scheduled_at} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="status">Status</Label>
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
                                                {status.replace('_', ' ')}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.status} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="notes">Notes</Label>
                                <Textarea
                                    id="notes"
                                    value={data.notes}
                                    onChange={(e) =>
                                        setData('notes', e.target.value)
                                    }
                                    placeholder="Any special requests or notes..."
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
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing || !isDirty}>
                            <Save className="mr-2 h-4 w-4" />
                            {processing ? 'Saving...' : 'Save Changes'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
