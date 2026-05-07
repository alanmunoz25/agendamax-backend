import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { LoyaltyProgressBar, type LoyaltyProgress } from '@/components/loyalty-progress-bar';
import type { User, Appointment, Stamp } from '@/types/models';
import { Users, Mail, Phone, Calendar, Award, Clock, AlertTriangle } from 'lucide-react';
import { format } from 'date-fns';
import { useTranslation } from 'react-i18next';
import { usePage } from '@inertiajs/react';
import { UnblockModal } from './components/UnblockModal';

interface ClientData extends User {
    pivot_status: 'active' | 'blocked' | 'left' | null;
    blocked_reason: string | null;
    blocked_at: string | null;
}

interface Props {
    client: ClientData;
    loyalty_progress: LoyaltyProgress;
    recent_appointments: Appointment[];
    recent_stamps: Stamp[];
    can: {
        block: boolean;
    };
}

export default function ClientShow({
    client,
    loyalty_progress,
    recent_appointments,
    can,
}: Props) {
    const { t } = useTranslation();
    const { props } = usePage();
    const businessId = (props.auth as { user: { business_id: number } })?.user?.business_id;
    const [showUnblockModal, setShowUnblockModal] = useState(false);

    const getStatusClass = (status: string) => {
        switch (status) {
            case 'completed':
                return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
            case 'confirmed':
                return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
            case 'cancelled':
                return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
            default:
                return 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200';
        }
    };

    const getStatusLabel = (status: string) => {
        const map: Record<string, string> = {
            completed: t('appointments.status_completed'),
            confirmed: t('appointments.status_confirmed'),
            cancelled: t('appointments.status_cancelled'),
            pending: t('appointments.status_pending'),
            in_progress: t('appointments.status_in_progress'),
        };
        return map[status] ?? status;
    };

    return (
        <AppLayout
            title={client.name}
            breadcrumbs={[
                { label: t('breadcrumbs.dashboard'), href: '/dashboard' },
                { label: t('breadcrumbs.clients'), href: '/clients' },
                { label: client.name },
            ]}
        >
            <div className="space-y-6">
                {/* Blocked banner */}
                {client.pivot_status === 'blocked' && (
                    <div className="flex items-start justify-between gap-4 rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950">
                        <div className="flex items-start gap-3">
                            <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />
                            <div className="space-y-1">
                                <p className="text-sm font-semibold text-amber-800 dark:text-amber-200">
                                    Este cliente está bloqueado en tu negocio
                                </p>
                                {client.blocked_reason && (
                                    <p className="text-sm text-amber-700 dark:text-amber-300">
                                        Razón: &ldquo;{client.blocked_reason}&rdquo;
                                    </p>
                                )}
                                {client.blocked_at && (
                                    <p className="text-xs text-amber-600 dark:text-amber-400">
                                        Bloqueado el:{' '}
                                        {format(new Date(client.blocked_at), 'dd/MM/yyyy HH:mm')}
                                    </p>
                                )}
                            </div>
                        </div>
                        {can.block && (
                            <Button
                                variant="outline"
                                size="sm"
                                className="shrink-0 border-amber-300 text-amber-800 hover:bg-amber-100 dark:border-amber-700 dark:text-amber-200 dark:hover:bg-amber-900"
                                onClick={() => setShowUnblockModal(true)}
                            >
                                Desbloquear
                            </Button>
                        )}
                    </div>
                )}

                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <div className="h-16 w-16 rounded-full bg-primary/10 flex items-center justify-center">
                            <Users className="h-8 w-8 text-primary" />
                        </div>
                        <div>
                            <h1 className="text-3xl font-bold tracking-tight text-foreground">
                                {client.name}
                            </h1>
                            <p className="text-sm text-muted-foreground">{t('clients.profile_subtitle')}</p>
                        </div>
                    </div>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    {/* Contact Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('clients.contact_card_title')}</CardTitle>
                            <CardDescription>{t('clients.contact_card_desc')}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center gap-3">
                                <Mail className="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <p className="text-sm font-medium">{t('common.email')}</p>
                                    <p className="text-sm text-muted-foreground">{client.email}</p>
                                </div>
                            </div>
                            {client.phone && (
                                <div className="flex items-center gap-3">
                                    <Phone className="h-5 w-5 text-muted-foreground" />
                                    <div>
                                        <p className="text-sm font-medium">{t('common.phone')}</p>
                                        <p className="text-sm text-muted-foreground">{client.phone}</p>
                                    </div>
                                </div>
                            )}
                            <div className="flex items-center gap-3">
                                <Calendar className="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <p className="text-sm font-medium">{t('clients.member_since')}</p>
                                    <p className="text-sm text-muted-foreground">
                                        {format(new Date(client.created_at), 'dd/MM/yyyy')}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Loyalty Progress */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Award className="h-5 w-5" />
                                {t('clients.loyalty_card_title')}
                            </CardTitle>
                            <CardDescription>{t('clients.loyalty_card_desc')}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <LoyaltyProgressBar progress={loyalty_progress} />
                        </CardContent>
                    </Card>
                </div>

                {/* Recent Appointments */}
                <Card>
                    <CardHeader>
                        <CardTitle>{t('clients.recent_appointments_title')}</CardTitle>
                        <CardDescription>{t('clients.recent_appointments_desc')}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {recent_appointments && recent_appointments.length > 0 ? (
                            <div className="space-y-3">
                                {recent_appointments.map((appointment) => (
                                    <div
                                        key={appointment.id}
                                        className="flex items-center justify-between border rounded-lg p-4"
                                    >
                                        <div className="flex items-start gap-3">
                                            <Clock className="h-5 w-5 text-muted-foreground mt-0.5" />
                                            <div>
                                                <p className="font-medium">
                                                    {appointment.service?.name}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {t('clients.appointment_with', { name: appointment.employee?.user?.name })}
                                                </p>
                                                <p className="text-xs text-muted-foreground mt-1">
                                                    {format(
                                                        new Date(appointment.scheduled_at),
                                                        'dd/MM/yyyy • HH:mm'
                                                    )}
                                                </p>
                                            </div>
                                        </div>
                                        <div className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${getStatusClass(appointment.status)}`}>
                                            {getStatusLabel(appointment.status)}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-center py-8 text-sm text-muted-foreground">
                                {t('clients.no_appointments')}
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Unblock Modal */}
            <UnblockModal
                client={client}
                businessId={businessId}
                open={showUnblockModal}
                onClose={() => setShowUnblockModal(false)}
            />
        </AppLayout>
    );
}
