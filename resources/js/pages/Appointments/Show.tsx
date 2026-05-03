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
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { ConfirmationModal } from '@/components/confirmation-modal';
import { CheckoutDrawer } from '@/components/pos/checkout-drawer';
import type { Appointment, Service } from '@/types/models';
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
    FilePlus,
    Loader,
    ShoppingCart,
} from 'lucide-react';

interface EcfSummary {
    id: number;
    ncf: string;
    status: string;
    issued_at: string;
}

interface Props {
    appointment: Appointment & { ticket_id: number | null };
    can: {
        edit: boolean;
        cancel: boolean;
        issue_ecf: boolean;
        checkout: boolean;
    };
    ecf?: EcfSummary | null;
    employees_for_checkout: Array<{ id: number; user: { name: string } }>;
    ecf_enabled: boolean;
}

export default function ShowAppointment({ appointment, can, ecf, employees_for_checkout, ecf_enabled }: Props) {
    const [showCancelModal, setShowCancelModal] = useState(false);
    const [showEcfModal, setShowEcfModal] = useState(false);
    const [showCheckoutDrawer, setShowCheckoutDrawer] = useState(false);

    const appointmentServices: Array<{ description: string; qty: number; unit_price: string; discount_pct: string }> =
        (appointment.services ?? []).map((svc) => ({
            description: svc.name,
            qty: 1,
            unit_price: String(svc.price),
            discount_pct: '0',
        }));

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
                        {/* POS Checkout */}
                        {appointment.ticket_id !== null && (
                            <Badge className="bg-[var(--color-green-brand)]/10 text-[var(--color-green-brand)]">
                                ✓ Cobrada
                            </Badge>
                        )}
                        {can.checkout && appointment.ticket_id === null && appointment.status === 'completed' && (
                            <Button
                                onClick={() => setShowCheckoutDrawer(true)}
                                className="bg-[var(--color-amber-brand)] hover:bg-[var(--color-amber-brand)]/90 text-white"
                            >
                                <ShoppingCart className="mr-2 h-4 w-4" />
                                Cobrar ●
                            </Button>
                        )}
                        {can.checkout && appointment.ticket_id === null && appointment.status !== 'completed' && (
                            <Button variant="outline" onClick={() => setShowCheckoutDrawer(true)}>
                                <ShoppingCart className="mr-2 h-4 w-4" />
                                Cobrar
                            </Button>
                        )}

                        {/* e-CF Button */}
                        {!ecf && can.issue_ecf && (
                            <Button
                                variant="outline"
                                onClick={() => setShowEcfModal(true)}
                            >
                                <FilePlus className="mr-2 h-4 w-4" />
                                Emitir e-CF
                            </Button>
                        )}
                        {ecf && (ecf.status === 'draft' || ecf.status === 'signed' || ecf.status === 'sent') && (
                            <Button variant="outline" disabled>
                                <Loader className="mr-2 h-4 w-4 animate-spin" />
                                e-CF en proceso
                            </Button>
                        )}
                        {ecf && ecf.status === 'accepted' && (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.visit(
                                        `/admin/electronic-invoice/issued/${ecf.id}`
                                    )
                                }
                            >
                                <FileText className="mr-2 h-4 w-4" />
                                Ver e-CF #{ecf.ncf} →
                            </Button>
                        )}
                        {ecf && (ecf.status === 'rejected' || ecf.status === 'error') && (
                            <Button
                                variant="destructive"
                                onClick={() =>
                                    router.visit(
                                        `/admin/electronic-invoice/issued/${ecf.id}`
                                    )
                                }
                            >
                                <FileText className="mr-2 h-4 w-4" />
                                e-CF Rechazado — Ver →
                            </Button>
                        )}

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

            {/* e-CF Wizard Modal — redirect to create page with appointment context */}
            <Dialog open={showEcfModal} onOpenChange={setShowEcfModal}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Emitir Comprobante Fiscal Electrónico</DialogTitle>
                    </DialogHeader>
                    <div className="space-y-4 py-2">
                        <p className="text-sm text-muted-foreground">
                            Se abrirá el asistente de emisión de e-CF con los datos de este appointment pre-cargados.
                        </p>
                        <div className="rounded-md bg-muted p-3 text-sm">
                            <p><span className="text-muted-foreground">Cliente:</span> {appointment.client?.name}</p>
                            {(appointment.services?.length ?? 0) > 0 && (
                                <p><span className="text-muted-foreground">Servicios:</span> {appointment.services?.map((s) => s.name).join(', ')}</p>
                            )}
                        </div>
                        <div className="flex justify-end gap-3">
                            <Button variant="outline" onClick={() => setShowEcfModal(false)}>
                                Cancelar
                            </Button>
                            <Button
                                onClick={() => {
                                    setShowEcfModal(false);
                                    router.visit('/admin/electronic-invoice/issued/create');
                                }}
                            >
                                <FilePlus className="mr-2 h-4 w-4" />
                                Ir al Asistente →
                            </Button>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>

            {/* POS Checkout Drawer */}
            <CheckoutDrawer
                open={showCheckoutDrawer}
                onOpenChange={setShowCheckoutDrawer}
                source="appointment"
                appointmentId={appointment.id}
                initialItems={(appointment.services ?? []).map((svc) => ({
                    id: svc.id,
                    type: 'service' as const,
                    name: svc.name,
                    unit_price: String(svc.price),
                    qty: 1,
                }))}
                client={appointment.client ?? null}
                employee={appointment.employee ?? null}
                employees={employees_for_checkout}
                ecfEnabled={ecf_enabled}
                onSuccess={() => {
                    setShowCheckoutDrawer(false);
                    router.reload();
                }}
            />
        </AppLayout>
    );
}
