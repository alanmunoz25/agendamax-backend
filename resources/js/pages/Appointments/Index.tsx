import { useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { DataTable, type Column } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { ConfirmationModal } from '@/components/confirmation-modal';
import type { Appointment, Employee } from '@/types/models';
import type { Service } from '@/types/models';
import type { PaginatedData } from '@/types/pagination';

type AppointmentWithEcf = Appointment & { ecf_ncf?: string | null };
import { Plus, Calendar, List, X, Clock, User as UserIcon, FileText } from 'lucide-react';

interface Props {
    appointments: PaginatedData<AppointmentWithEcf> | AppointmentWithEcf[];
    employees: Employee[];
    services: Service[];
    filters: {
        search?: string;
        status?: string;
        employee_id?: string;
        service_id?: string;
        start_date?: string;
        end_date?: string;
        view?: 'list' | 'calendar';
        month?: string;
    };
    statuses: string[];
    can: {
        create: boolean;
        manage: boolean;
        cancel: boolean;
        filter_employees: boolean;
        filter_services: boolean;
    };
}

export default function AppointmentsIndex({
    appointments,
    employees,
    services,
    filters,
    statuses,
    can,
}: Props) {
    const [cancelAppointment, setCancelAppointment] =
        useState<AppointmentWithEcf | null>(null);
    const [viewMode, setViewMode] = useState<'list' | 'calendar'>(
        filters.view || 'list'
    );

    const isCalendarView = viewMode === 'calendar';
    const appointmentList = (
        Array.isArray(appointments)
            ? appointments
            : appointments.data
    ) as AppointmentWithEcf[];

    const handleSearch = (value: string) => {
        router.get(
            '/appointments',
            { ...filters, search: value || undefined },
            { preserveState: true, replace: true }
        );
    };

    const handleFilter = (key: string, value: string) => {
        router.get(
            '/appointments',
            { ...filters, [key]: value === 'all' ? undefined : value || undefined },
            { preserveState: true, replace: true }
        );
    };

    const handleViewChange = (view: 'list' | 'calendar') => {
        setViewMode(view);
        router.get(
            '/appointments',
            { ...filters, view },
            { preserveState: true, replace: true }
        );
    };

    const handleClearFilters = () => {
        router.get(
            '/appointments',
            { view: viewMode },
            { preserveState: true, replace: true }
        );
    };

    const handleCancel = () => {
        if (!cancelAppointment) return;

        router.delete(`/appointments/${cancelAppointment.id}`, {
            onSuccess: () => setCancelAppointment(null),
        });
    };

    const getStatusColor = (status: string) => {
        const colors: Record<string, 'default' | 'success' | 'destructive'> = {
            pending: 'default',
            confirmed: 'default',
            in_progress: 'default',
            completed: 'success',
            cancelled: 'destructive',
        };
        return colors[status] || 'default';
    };

    const columns: Column<AppointmentWithEcf>[] = [
        {
            key: 'client',
            label: 'Client',
            render: (appointment) => (
                <div className="flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-full bg-muted">
                        <UserIcon className="h-5 w-5 text-muted-foreground" />
                    </div>
                    <div>
                        <div className="font-medium text-foreground">
                            {appointment.client?.name}
                        </div>
                        <div className="text-sm text-muted-foreground">
                            {appointment.client?.email}
                        </div>
                    </div>
                </div>
            ),
        },
        {
            key: 'service',
            label: 'Service',
            render: (appointment) => (
                <div>
                    <div className="font-medium text-foreground">
                        {appointment.service?.name}
                    </div>
                    {appointment.service?.category && (
                        <div className="text-sm text-muted-foreground">
                            {appointment.service.category}
                        </div>
                    )}
                </div>
            ),
        },
        {
            key: 'employee',
            label: 'Employee',
            render: (appointment) => (
                <div className="text-sm text-foreground">
                    {appointment.employee?.user?.name}
                </div>
            ),
        },
        {
            key: 'scheduled_at',
            label: 'Date & Time',
            sortable: true,
            render: (appointment) => (
                <div>
                    <div className="text-sm font-medium text-foreground">
                        {new Date(
                            appointment.scheduled_at
                        ).toLocaleDateString()}
                    </div>
                    <div className="flex items-center gap-1 text-sm text-muted-foreground">
                        <Clock className="h-3 w-3" />
                        {new Date(
                            appointment.scheduled_at
                        ).toLocaleTimeString([], {
                            hour: '2-digit',
                            minute: '2-digit',
                        })}
                    </div>
                </div>
            ),
        },
        {
            key: 'created_at',
            label: 'Created',
            render: (appointment) => (
                <div className="text-sm text-muted-foreground">
                    {new Date(appointment.created_at).toLocaleDateString()}
                </div>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            render: (appointment) => (
                <Badge variant={getStatusColor(appointment.status)}>
                    {appointment.status.replace('_', ' ')}
                </Badge>
            ),
        },
        {
            key: 'actions',
            label: '',
            render: (appointment) => (
                <div className="flex items-center justify-end gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            router.visit(`/appointments/${appointment.id}`)
                        }
                    >
                        View
                    </Button>
                    {/* e-CF badge / indicator */}
                    {appointment.ecf_ncf ? (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                router.visit(
                                    `/admin/electronic-invoice/issued?search=${appointment.ecf_ncf}`
                                )
                            }
                            className="gap-1.5 text-xs text-primary"
                        >
                            <FileText className="h-3.5 w-3.5" />
                            e-CF {appointment.ecf_ncf}
                        </Button>
                    ) : appointment.status === 'completed' ? (
                        <span
                            className="h-2 w-2 rounded-full bg-muted-foreground"
                            title="Sin e-CF emitido"
                        />
                    ) : null}
                    {can.cancel &&
                        appointment.status !== 'cancelled' &&
                        appointment.status !== 'completed' && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setCancelAppointment(appointment)}
                            >
                                Cancel
                            </Button>
                        )}
                </div>
            ),
        },
    ];

    const hasFilters =
        filters.search ||
        filters.status ||
        filters.employee_id ||
        filters.service_id ||
        filters.start_date ||
        filters.end_date;

    return (
        <AppLayout
            title="Appointments"
            breadcrumbs={[
                { label: 'Dashboard', href: '/dashboard' },
                { label: 'Appointments' },
            ]}
        >
            <div className="mx-auto max-w-7xl space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight text-foreground">
                            Appointments
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {can.manage ? 'Manage and view all appointments' : 'View your appointments'}
                        </p>
                    </div>
                    {can.create && (
                        <Button onClick={() => router.visit('/appointments/create')}>
                            <Plus className="mr-2 h-4 w-4" />
                            New Appointment
                        </Button>
                    )}
                </div>

                {/* Filters */}
                <div className="flex flex-col gap-4 rounded-lg border border-border bg-card p-4">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <Button
                                variant={viewMode === 'list' ? 'default' : 'outline'}
                                size="sm"
                                onClick={() => handleViewChange('list')}
                            >
                                <List className="mr-2 h-4 w-4" />
                                List
                            </Button>
                            <Button
                                variant={
                                    viewMode === 'calendar' ? 'default' : 'outline'
                                }
                                size="sm"
                                onClick={() => handleViewChange('calendar')}
                            >
                                <Calendar className="mr-2 h-4 w-4" />
                                Calendar
                            </Button>
                        </div>
                        {hasFilters && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={handleClearFilters}
                            >
                                <X className="mr-2 h-4 w-4" />
                                Clear Filters
                            </Button>
                        )}
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {/* Search */}
                        <Input
                            placeholder="Search by client..."
                            value={filters.search || ''}
                            onChange={(e) => handleSearch(e.target.value)}
                        />

                        {/* Status Filter */}
                        <Select
                            value={filters.status || ''}
                            onValueChange={(value) =>
                                handleFilter('status', value)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="All Statuses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Statuses</SelectItem>
                                {statuses.map((status) => (
                                    <SelectItem key={status} value={status}>
                                        {status.replace('_', ' ')}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        {/* Employee Filter */}
                        {can.filter_employees && (
                            <Select
                                value={filters.employee_id || ''}
                                onValueChange={(value) =>
                                    handleFilter('employee_id', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="All Employees" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Employees</SelectItem>
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
                        )}

                        {/* Service Filter */}
                        {can.filter_services && (
                            <Select
                                value={filters.service_id || ''}
                                onValueChange={(value) =>
                                    handleFilter('service_id', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="All Services" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Services</SelectItem>
                                    {services.map((service) => (
                                        <SelectItem
                                            key={service.id}
                                            value={service.id.toString()}
                                        >
                                            {service.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                    </div>
                </div>

                {/* Content */}
                {appointmentList.length === 0 ? (
                    <EmptyState
                        icon={Calendar}
                        title={hasFilters ? 'No appointments found' : 'No appointments yet'}
                        description={
                            hasFilters
                                ? 'Try adjusting your filters to find what you are looking for.'
                                : 'Get started by creating your first appointment.'
                        }
                        action={
                            !hasFilters && can.create
                                ? {
                                      label: 'Create Appointment',
                                      onClick: () =>
                                          router.visit('/appointments/create'),
                                  }
                                : undefined
                        }
                    />
                ) : isCalendarView ? (
                    <div className="rounded-lg border border-border bg-card p-4">
                        <p className="text-center text-muted-foreground">
                            Calendar view coming soon...
                        </p>
                        <p className="mt-2 text-center text-sm text-muted-foreground">
                            Showing {appointmentList.length} appointments
                        </p>
                    </div>
                ) : (
                    <DataTable<AppointmentWithEcf>
                        columns={columns}
                        data={appointmentList}
                        pagination={
                            !Array.isArray(appointments)
                                ? {
                                      currentPage: appointments.current_page,
                                      lastPage: appointments.last_page,
                                      onPageChange: (page) =>
                                          router.get(
                                              '/appointments',
                                              { ...filters, page },
                                              { preserveState: true, replace: true }
                                          ),
                                  }
                                : undefined
                        }
                    />
                )}
            </div>

            {/* Cancel Confirmation Modal */}
            <ConfirmationModal
                open={cancelAppointment !== null}
                onOpenChange={(open) => !open && setCancelAppointment(null)}
                title="Cancel Appointment"
                description={
                    <div className="space-y-2">
                        <p>
                            Are you sure you want to cancel this appointment with{' '}
                            <span className="font-semibold">
                                {cancelAppointment?.client?.name}
                            </span>
                            ?
                        </p>
                        <p className="text-sm text-muted-foreground">
                            The client will be notified of the cancellation.
                        </p>
                    </div>
                }
                confirmLabel="Cancel Appointment"
                cancelLabel="Keep Appointment"
                onConfirm={handleCancel}
                variant="destructive"
            />
        </AppLayout>
    );
}
