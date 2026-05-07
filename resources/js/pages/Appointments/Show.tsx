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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { ConfirmationModal } from '@/components/confirmation-modal';
import { CheckoutDrawer } from '@/components/pos/checkout-drawer';
import { AddAppointmentServiceModal, type EmployeeWithServices } from './components/AddAppointmentServiceModal';
import { AssignEmployeeModal, type AppointmentServiceForAssign } from './components/AssignEmployeeModal';
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
    FilePlus,
    Loader,
    ShoppingCart,
    AlertTriangle,
    Plus,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { format } from 'date-fns';

interface EcfSummary {
    id: number;
    ncf: string;
    status: string;
    issued_at: string;
}

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
    appointment_service_lines: AppointmentServiceLine[];
    employees_with_services: EmployeeWithServices[];
}

export default function ShowAppointment({
    appointment,
    can,
    ecf,
    employees_for_checkout,
    ecf_enabled,
    appointment_service_lines,
    employees_with_services,
}: Props) {
    const { t } = useTranslation();
    const [showCancelModal, setShowCancelModal] = useState(false);
    const [showEcfModal, setShowEcfModal] = useState(false);
    const [showCheckoutDrawer, setShowCheckoutDrawer] = useState(false);
    const [showAddServiceModal, setShowAddServiceModal] = useState(false);
    const [showAssignModal, setShowAssignModal] = useState(false);
    const [assignTargetLine, setAssignTargetLine] = useState<AppointmentServiceForAssign | null>(null);

    const openAssignModal = (line: AppointmentServiceForAssign) => {
        setAssignTargetLine(line);
        setShowAssignModal(true);
    };

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

    const isEditable =
        appointment.status !== 'cancelled' &&
        appointment.status !== 'completed';

    return (
        <AppLayout
            title={`${t('appointments.show_title')} #${appointment.id}`}
            breadcrumbs={[
                { label: t('breadcrumbs.dashboard'), href: '/dashboard' },
                { label: t('breadcrumbs.appointments'), href: '/appointments' },
                { label: `${t('appointments.show_title')} #${appointment.id}` },
            ]}
        >
            <div className="mx-auto max-w-4xl space-y-6">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-3xl font-bold tracking-tight text-foreground">
                                {t('appointments.show_title')} #{appointment.id}
                            </h1>
                            <Badge variant={getStatusColor(appointment.status)}>
                                {getStatusLabel(appointment.status)}
                            </Badge>
                        </div>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {t('appointments.scheduled_for')}{' '}
                            {format(new Date(appointment.scheduled_at), 'EEEE, dd/MM/yyyy')}
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        {/* POS Checkout */}
                        {appointment.ticket_id !== null && (
                            <Badge className="bg-[var(--color-green-brand)]/10 text-[var(--color-green-brand)]">
                                ✓ {t('appointments.collected_badge')}
                            </Badge>
                        )}
                        {can.checkout && appointment.ticket_id === null && appointment.status === 'completed' && (
                            <Button
                                onClick={() => setShowCheckoutDrawer(true)}
                                className="bg-[var(--color-amber-brand)] hover:bg-[var(--color-amber-brand)]/90 text-white"
                            >
                                <ShoppingCart className="mr-2 h-4 w-4" />
                                {t('pos.charge')} ●
                            </Button>
                        )}
                        {can.checkout && appointment.ticket_id === null && appointment.status !== 'completed' && (
                            <Button variant="outline" onClick={() => setShowCheckoutDrawer(true)}>
                                <ShoppingCart className="mr-2 h-4 w-4" />
                                {t('pos.charge')}
                            </Button>
                        )}

                        {/* e-CF Button */}
                        {!ecf && can.issue_ecf && (
                            <Button
                                variant="outline"
                                onClick={() => setShowEcfModal(true)}
                            >
                                <FilePlus className="mr-2 h-4 w-4" />
                                {t('appointments.issue_ecf')}
                            </Button>
                        )}
                        {ecf && (ecf.status === 'draft' || ecf.status === 'signed' || ecf.status === 'sent') && (
                            <Button variant="outline" disabled>
                                <Loader className="mr-2 h-4 w-4 animate-spin" />
                                {t('appointments.ecf_in_progress')}
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
                                {t('appointments.view_ecf', { ncf: ecf.ncf })} →
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
                                {t('appointments.ecf_rejected')} →
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
                                {t('common.edit')}
                            </Button>
                        )}
                        {can.cancel && isEditable && (
                            <Button
                                variant="outline"
                                onClick={() => setShowCancelModal(true)}
                            >
                                <Trash2 className="mr-2 h-4 w-4" />
                                {t('appointments.cancel_btn')}
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
                                <CardTitle>{t('appointments.client_card_title')}</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    {t('common.name')}
                                </p>
                                <p className="mt-1 text-base text-foreground">
                                    {appointment.client?.name}
                                </p>
                            </div>
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    {t('common.email')}
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
                                        {t('common.phone')}
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

                    {/* Services — full width, Mejoras #3 + #4 */}
                    <Card className="md:col-span-2">
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <Briefcase className="h-5 w-5 text-muted-foreground" />
                                    <CardTitle>
                                        {appointment_service_lines.length > 0
                                            ? `${t('appointments.services_card_title')} (${appointment_service_lines.length})`
                                            : t('appointments.services_card_title')}
                                    </CardTitle>
                                </div>
                                {can.edit && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setShowAddServiceModal(true)}
                                    >
                                        <Plus className="mr-2 h-4 w-4" />
                                        {t('appointments.add_service_btn')}
                                    </Button>
                                )}
                            </div>
                        </CardHeader>
                        <CardContent>
                            {appointment_service_lines.length === 0 ? (
                                <div className="py-10 text-center">
                                    <Briefcase className="mx-auto mb-3 h-10 w-10 text-muted-foreground/50" />
                                    <p className="text-sm text-muted-foreground">
                                        {t('appointments.no_services_added')}
                                    </p>
                                    {can.edit && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            className="mt-4"
                                            onClick={() => setShowAddServiceModal(true)}
                                        >
                                            <Plus className="mr-2 h-4 w-4" />
                                            {t('appointments.add_service_btn')}
                                        </Button>
                                    )}
                                </div>
                            ) : (
                                <>
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
                                                            {line.service.category && (
                                                                <span className="ml-2 text-xs text-muted-foreground">
                                                                    {line.service.category}
                                                                </span>
                                                            )}
                                                        </TableCell>
                                                        <TableCell>
                                                            {line.employee ? (
                                                                <div className="flex flex-col gap-1">
                                                                    <span className="flex items-center gap-1.5">
                                                                        <span className="inline-block h-2 w-2 rounded-full bg-green-500" />
                                                                        {line.employee.name}
                                                                    </span>
                                                                    {can.edit && (
                                                                        <button
                                                                            type="button"
                                                                            className="text-left text-xs text-muted-foreground underline-offset-2 hover:text-foreground hover:underline"
                                                                            onClick={() => openAssignModal({ id: line.id, service: line.service, employee: line.employee })}
                                                                        >
                                                                            {t('appointments.service.change_employee')} →
                                                                        </button>
                                                                    )}
                                                                </div>
                                                            ) : (
                                                                <div className="flex flex-col gap-1">
                                                                    <span className="flex items-center gap-1 text-amber-600 dark:text-amber-400">
                                                                        <AlertTriangle className="h-4 w-4" />
                                                                        <span className="text-sm">
                                                                            {t('appointments.service.no_employee_warning')}
                                                                        </span>
                                                                    </span>
                                                                    {can.edit && (
                                                                        <button
                                                                            type="button"
                                                                            className="text-left text-xs text-orange-600 underline-offset-2 hover:text-orange-700 hover:underline"
                                                                            onClick={() => openAssignModal({ id: line.id, service: line.service, employee: null })}
                                                                        >
                                                                            {t('appointments.service.assign_employee')} →
                                                                        </button>
                                                                    )}
                                                                </div>
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
                                                {line.service.category && (
                                                    <p className="text-xs text-muted-foreground">
                                                        {line.service.category}
                                                    </p>
                                                )}
                                                <div className="mt-2 space-y-1 text-sm">
                                                    {line.employee ? (
                                                        <div className="flex flex-col gap-0.5">
                                                            <p className="flex items-center gap-1.5 text-foreground">
                                                                <span className="inline-block h-2 w-2 rounded-full bg-green-500" />
                                                                {line.employee.name}
                                                            </p>
                                                            {can.edit && (
                                                                <button
                                                                    type="button"
                                                                    className="text-left text-xs text-muted-foreground underline-offset-2 hover:text-foreground hover:underline"
                                                                    onClick={() => openAssignModal({ id: line.id, service: line.service, employee: line.employee })}
                                                                >
                                                                    {t('appointments.service.change_employee')} →
                                                                </button>
                                                            )}
                                                        </div>
                                                    ) : (
                                                        <div className="flex flex-col gap-0.5">
                                                            <p className="flex items-center gap-1 text-amber-600 dark:text-amber-400">
                                                                <AlertTriangle className="h-4 w-4" />
                                                                {t('appointments.service.no_employee_warning')}
                                                            </p>
                                                            {can.edit && (
                                                                <button
                                                                    type="button"
                                                                    className="text-left text-xs text-orange-600 underline-offset-2 hover:text-orange-700 hover:underline"
                                                                    onClick={() => openAssignModal({ id: line.id, service: line.service, employee: null })}
                                                                >
                                                                    {t('appointments.service.assign_employee')} →
                                                                </button>
                                                            )}
                                                        </div>
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
                                </>
                            )}
                        </CardContent>
                    </Card>

                    {/* Additional Information — schedule + compact layout */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Calendar className="h-5 w-5 text-muted-foreground" />
                                <CardTitle>{t('appointments.additional_info_card_title')}</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    {t('common.date')}
                                </p>
                                <p className="mt-1 text-base text-foreground">
                                    {format(new Date(appointment.scheduled_at), 'EEEE, dd/MM/yyyy')}
                                </p>
                            </div>
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    {t('appointments.time_label')}
                                </p>
                                <div className="mt-1 flex items-center gap-1 text-base text-foreground">
                                    <Clock className="h-4 w-4" />
                                    {format(new Date(appointment.scheduled_at), 'HH:mm')}
                                    {appointment.scheduled_until && (
                                        <>
                                            {' - '}
                                            {format(new Date(appointment.scheduled_until), 'HH:mm')}
                                        </>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Visit Information (if exists) */}
                    {appointment.visit && (
                        <Card className="md:col-span-2">
                            <CardHeader>
                                <div className="flex items-center gap-2">
                                    <QrCode className="h-5 w-5 text-muted-foreground" />
                                    <CardTitle>{t('appointments.visit_card_title')}</CardTitle>
                                </div>
                                <CardDescription>
                                    {t('appointments.visit_card_desc')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">
                                        {t('appointments.verified_at')}
                                    </p>
                                    <p className="mt-1 text-base text-foreground">
                                        {format(new Date(appointment.visit.verified_at), 'dd/MM/yyyy HH:mm')}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">
                                        {t('appointments.stamp_earned')}
                                    </p>
                                    <p className="mt-1 text-base text-foreground">
                                        {t('common.yes')}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Metadata */}
                    <Card className="md:col-span-2">
                        <CardHeader>
                            <CardTitle>{t('employees.additional_info_title')}</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-3">
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    {t('common.status')}
                                </p>
                                <p className="mt-1 text-base">
                                    <Badge
                                        variant={getStatusColor(appointment.status)}
                                    >
                                        {getStatusLabel(appointment.status)}
                                    </Badge>
                                </p>
                            </div>
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    {t('common.created_at')}
                                </p>
                                <p className="mt-1 text-base text-foreground">
                                    {format(new Date(appointment.created_at), 'dd/MM/yyyy')}
                                </p>
                            </div>
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    {t('common.updated_at')}
                                </p>
                                <p className="mt-1 text-base text-foreground">
                                    {format(new Date(appointment.updated_at), 'dd/MM/yyyy')}
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
                title={t('appointments.cancel_modal_title')}
                description={
                    <div className="space-y-2">
                        <p>
                            {t('appointments.cancel_modal_description', { client: appointment.client?.name })}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            {t('appointments.cancel_modal_note')}
                        </p>
                    </div>
                }
                confirmLabel={t('appointments.cancel_confirm')}
                cancelLabel={t('appointments.cancel_keep')}
                onConfirm={handleCancel}
                variant="destructive"
            />

            {/* e-CF Wizard Modal */}
            <Dialog open={showEcfModal} onOpenChange={setShowEcfModal}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>{t('appointments.ecf_modal_title')}</DialogTitle>
                    </DialogHeader>
                    <div className="space-y-4 py-2">
                        <p className="text-sm text-muted-foreground">
                            {t('appointments.ecf_modal_desc')}
                        </p>
                        <div className="rounded-md bg-muted p-3 text-sm">
                            <p><span className="text-muted-foreground">{t('appointments.client_label')}:</span> {appointment.client?.name}</p>
                            {(appointment.services?.length ?? 0) > 0 && (
                                <p><span className="text-muted-foreground">{t('appointments.col_service')}:</span> {appointment.services?.map((s) => s.name).join(', ')}</p>
                            )}
                        </div>
                        <div className="flex justify-end gap-3">
                            <Button variant="outline" onClick={() => setShowEcfModal(false)}>
                                {t('common.cancel')}
                            </Button>
                            <Button
                                onClick={() => {
                                    setShowEcfModal(false);
                                    router.visit('/admin/electronic-invoice/issued/create');
                                }}
                            >
                                <FilePlus className="mr-2 h-4 w-4" />
                                {t('appointments.go_to_ecf_wizard')} →
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
                initialItems={appointment_service_lines.map((line) => ({
                    id: line.service.id,
                    type: 'service' as const,
                    name: line.service.name,
                    unit_price: String(line.service.price),
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

            {/* Add Service Modal — Mejora #4 */}
            <AddAppointmentServiceModal
                open={showAddServiceModal}
                onOpenChange={setShowAddServiceModal}
                appointmentId={appointment.id}
                existingServiceIds={appointment_service_lines.map((l) => l.service.id)}
                employees={employees_with_services}
            />

            {/* Assign / Change Employee Modal */}
            <AssignEmployeeModal
                open={showAssignModal}
                onOpenChange={(open) => {
                    setShowAssignModal(open);
                    if (!open) setAssignTargetLine(null);
                }}
                appointmentId={appointment.id}
                appointmentService={assignTargetLine}
                employees={employees_with_services}
            />
        </AppLayout>
    );
}
