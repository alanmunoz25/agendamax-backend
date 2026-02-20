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
import { ClientSearchSelect } from '@/components/client-search-select';
import type { Employee, Service, User } from '@/types/models';
import { CalendarPlus, Briefcase, Users, Clock } from 'lucide-react';

interface Props {
    employees: Employee[];
    services: Service[];
    clients: Pick<User, 'id' | 'name' | 'email' | 'phone'>[];
}

export default function CreateAppointment({
    employees,
    services,
    clients,
}: Props) {
    const { data, setData, post, processing, errors, isDirty } = useForm({
        client_id: '',
        service_id: '',
        employee_id: '',
        scheduled_at: '',
        notes: '',
    });

    const [selectedService, setSelectedService] = useState<Service | null>(null);
    const [availableSlots, setAvailableSlots] = useState<string[]>([]);
    const [loadingSlots, setLoadingSlots] = useState(false);

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
        post('/appointments');
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
            title="Create Appointment"
            breadcrumbs={[
                { label: 'Dashboard', href: '/dashboard' },
                { label: 'Appointments', href: '/appointments' },
                { label: 'Create' },
            ]}
        >
            <div className="mx-auto max-w-2xl space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-3xl font-bold tracking-tight text-foreground">
                        New Appointment
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Schedule a new appointment for a client
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    {/* Client Information */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Users className="h-5 w-5 text-muted-foreground" />
                                <CardTitle>Client Information</CardTitle>
                            </div>
                            <CardDescription>
                                Select the client for this appointment
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="client_id" required>
                                    Client
                                </Label>
                                <ClientSearchSelect
                                    clients={clients}
                                    value={
                                        data.client_id
                                            ? parseInt(data.client_id)
                                            : undefined
                                    }
                                    onChange={(value) =>
                                        setData('client_id', value.toString())
                                    }
                                    placeholder="Search and select a client"
                                />
                                <InputError message={errors.client_id} />
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
                                Choose the service and employee
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="service_id" required>
                                    Service
                                </Label>
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
                                <Label htmlFor="employee_id" required>
                                    Employee
                                </Label>
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
                                <CardTitle>Date & Time</CardTitle>
                            </div>
                            <CardDescription>
                                Choose when the appointment will take place
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="scheduled_at" required>
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
                                <p className="text-xs text-muted-foreground">
                                    Optional notes about the appointment
                                </p>
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
                            <CalendarPlus className="mr-2 h-4 w-4" />
                            {processing ? 'Creating...' : 'Create Appointment'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
