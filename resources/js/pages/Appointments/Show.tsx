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
import { ConfirmationModal } from '@/components/confirmation-modal';
import type { Appointment } from '@/types/models';
import {
    Edit,
    Trash2,
    Calendar,
    Clock,
    User as UserIcon,
    Briefcase,
    DollarSign,
    FileText,
    QrCode,
} from 'lucide-react';

interface Props {
    appointment: Appointment;
    can: {
        edit: boolean;
        cancel: boolean;
    };
}

export default function ShowAppointment({ appointment, can }: Props) {
    const [showCancelModal, setShowCancelModal] = useState(false);

    const handleCancel = () => {
        router.delete(`/appointments/${appointment.id}`, {
            onSuccess: () => router.visit('/appointments'),
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

    const isEditable =
        appointment.status !== 'cancelled' &&
        appointment.status !== 'completed';

    return (
        <AppLayout
            title={`Appointment #${appointment.id}`}
            breadcrumbs={[
                { label: 'Dashboard', href: '/dashboard' },
                { label: 'Appointments', href: '/appointments' },
                { label: `Appointment #${appointment.id}` },
            ]}
        >
            <div className="mx-auto max-w-4xl space-y-6">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-3xl font-bold tracking-tight text-foreground">
                                Appointment #{appointment.id}
                            </h1>
                            <Badge variant={getStatusColor(appointment.status)}>
                                {appointment.status.replace('_', ' ')}
                            </Badge>
                        </div>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Scheduled for{' '}
                            {new Date(
                                appointment.scheduled_at
                            ).toLocaleDateString('en-US', {
                                weekday: 'long',
                                year: 'numeric',
                                month: 'long',
                                day: 'numeric',
                            })}
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        {can.edit && isEditable && (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.visit(
                                        `/appointments/${appointment.id}/edit`
                                    )
                                }
                            >
                                <Edit className="mr-2 h-4 w-4" />
                                Edit
                            </Button>
                        )}
                        {can.cancel && isEditable && (
                            <Button
                                variant="outline"
                                onClick={() => setShowCancelModal(true)}
                            >
                                <Trash2 className="mr-2 h-4 w-4" />
                                Cancel
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    {/* Client Information */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <UserIcon className="h-5 w-5 text-muted-foreground" />
                                <CardTitle>Client Information</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    Name
                                </p>
                                <p className="mt-1 text-base text-foreground">
                                    {appointment.client?.name}
                                </p>
                            </div>
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    Email
                                </p>
                                <a
                                    href={`mailto:${appointment.client?.email}`}
                                    className="mt-1 text-base text-primary hover:underline"
                                >
                                    {appointment.client?.email}
                                </a>
                            </div>
                            {appointment.client?.phone && (
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Phone
                                    </p>
                                    <a
                                        href={`tel:${appointment.client.phone}`}
                                        className="mt-1 text-base text-primary hover:underline"
                                    >
                                        {appointment.client.phone}
                                    </a>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Service Information */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Briefcase className="h-5 w-5 text-muted-foreground" />
                                <CardTitle>
                                    {appointment.services && appointment.services.length > 1
                                        ? `Services (${appointment.services.length})`
                                        : 'Service Details'}
                                </CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {appointment.services && appointment.services.length > 0 ? (
                                appointment.services.map((svc, index) => (
                                    <div
                                        key={svc.id}
                                        className={index > 0 ? 'border-t pt-4' : ''}
                                    >
                                        <div>
                                            <p className="text-sm font-medium text-muted-foreground">
                                                Service
                                            </p>
                                            <p className="mt-1 text-base text-foreground">
                                                {svc.name}
                                            </p>
                                            {svc.category && (
                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    {svc.category}
                                                </p>
                                            )}
                                        </div>
                                        <div className="mt-2 grid grid-cols-2 gap-4">
                                            <div>
                                                <p className="text-sm font-medium text-muted-foreground">
                                                    Duration
                                                </p>
                                                <div className="mt-1 flex items-center gap-1 text-base text-foreground">
                                                    <Clock className="h-4 w-4" />
                                                    {svc.duration} min
                                                </div>
                                            </div>
                                            <div>
                                                <p className="text-sm font-medium text-muted-foreground">
                                                    Price
                                                </p>
                                                <div className="mt-1 flex items-center gap-1 text-base text-foreground">
                                                    <DollarSign className="h-4 w-4" />
                                                    {svc.price}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                ))
                            ) : appointment.service ? (
                                <>
                                    <div>
                                        <p className="text-sm font-medium text-muted-foreground">
                                            Service
                                        </p>
                                        <p className="mt-1 text-base text-foreground">
                                            {appointment.service.name}
                                        </p>
                                        {appointment.service.category && (
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {appointment.service.category}
                                            </p>
                                        )}
                                    </div>
                                    <div className="grid grid-cols-2 gap-4">
                                        <div>
                                            <p className="text-sm font-medium text-muted-foreground">
                                                Duration
                                            </p>
                                            <div className="mt-1 flex items-center gap-1 text-base text-foreground">
                                                <Clock className="h-4 w-4" />
                                                {appointment.service.duration} min
                                            </div>
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium text-muted-foreground">
                                                Price
                                            </p>
                                            <div className="mt-1 flex items-center gap-1 text-base text-foreground">
                                                <DollarSign className="h-4 w-4" />
                                                {appointment.service.price}
                                            </div>
                                        </div>
                                    </div>
                                </>
                            ) : null}
                        </CardContent>
                    </Card>

                    {/* Employee Information */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <UserIcon className="h-5 w-5 text-muted-foreground" />
                                <CardTitle>Service Provider</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center gap-3">
                                {appointment.employee?.photo_url ? (
                                    <img
                                        src={appointment.employee.photo_url}
                                        alt={appointment.employee.user?.name}
                                        className="h-12 w-12 rounded-full object-cover"
                                    />
                                ) : (
                                    <div className="flex h-12 w-12 items-center justify-center rounded-full bg-muted">
                                        <UserIcon className="h-6 w-6 text-muted-foreground" />
                                    </div>
                                )}
                                <div>
                                    <p className="font-medium text-foreground">
                                        {appointment.employee?.user?.name}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {appointment.employee?.user?.email}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Schedule Information */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Calendar className="h-5 w-5 text-muted-foreground" />
                                <CardTitle>Schedule</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    Date
                                </p>
                                <p className="mt-1 text-base text-foreground">
                                    {new Date(
                                        appointment.scheduled_at
                                    ).toLocaleDateString('en-US', {
                                        weekday: 'long',
                                        year: 'numeric',
                                        month: 'long',
                                        day: 'numeric',
                                    })}
                                </p>
                            </div>
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    Time
                                </p>
                                <div className="mt-1 flex items-center gap-1 text-base text-foreground">
                                    <Clock className="h-4 w-4" />
                                    {new Date(
                                        appointment.scheduled_at
                                    ).toLocaleTimeString([], {
                                        hour: '2-digit',
                                        minute: '2-digit',
                                    })}
                                    {appointment.scheduled_until && (
                                        <>
                                            {' - '}
                                            {new Date(
                                                appointment.scheduled_until
                                            ).toLocaleTimeString([], {
                                                hour: '2-digit',
                                                minute: '2-digit',
                                            })}
                                        </>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Notes */}
                    {appointment.notes && (
                        <Card className="md:col-span-2">
                            <CardHeader>
                                <div className="flex items-center gap-2">
                                    <FileText className="h-5 w-5 text-muted-foreground" />
                                    <CardTitle>Notes</CardTitle>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm text-muted-foreground">
                                    {appointment.notes}
                                </p>
                            </CardContent>
                        </Card>
                    )}

                    {/* Visit Information (if exists) */}
                    {appointment.visit && (
                        <Card className="md:col-span-2">
                            <CardHeader>
                                <div className="flex items-center gap-2">
                                    <QrCode className="h-5 w-5 text-muted-foreground" />
                                    <CardTitle>Visit Verification</CardTitle>
                                </div>
                                <CardDescription>
                                    This appointment has been verified with a client
                                    visit
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Verified At
                                    </p>
                                    <p className="mt-1 text-base text-foreground">
                                        {new Date(
                                            appointment.visit.verified_at
                                        ).toLocaleString()}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Stamp Earned
                                    </p>
                                    <p className="mt-1 text-base text-foreground">
                                        Yes
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Metadata */}
                    <Card className="md:col-span-2">
                        <CardHeader>
                            <CardTitle>Additional Information</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-3">
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    Status
                                </p>
                                <p className="mt-1 text-base">
                                    <Badge
                                        variant={getStatusColor(appointment.status)}
                                    >
                                        {appointment.status.replace('_', ' ')}
                                    </Badge>
                                </p>
                            </div>
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    Created
                                </p>
                                <p className="mt-1 text-base text-foreground">
                                    {new Date(
                                        appointment.created_at
                                    ).toLocaleDateString()}
                                </p>
                            </div>
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    Last Updated
                                </p>
                                <p className="mt-1 text-base text-foreground">
                                    {new Date(
                                        appointment.updated_at
                                    ).toLocaleDateString()}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            {/* Cancel Confirmation Modal */}
            <ConfirmationModal
                open={showCancelModal}
                onOpenChange={setShowCancelModal}
                title="Cancel Appointment"
                description={
                    <div className="space-y-2">
                        <p>
                            Are you sure you want to cancel this appointment with{' '}
                            <span className="font-semibold">
                                {appointment.client?.name}
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
