import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { LoyaltyProgressBar, type LoyaltyProgress } from '@/components/loyalty-progress-bar';
import type { User, Appointment, Stamp } from '@/types/models';
import { Users, Mail, Phone, Calendar, Award, Clock } from 'lucide-react';
import { format } from 'date-fns';

interface Props {
    client: User;
    loyalty_progress: LoyaltyProgress;
    recent_appointments: Appointment[];
    recent_stamps: Stamp[];
}

export default function ClientShow({
    client,
    loyalty_progress,
    recent_appointments,
    recent_stamps,
}: Props) {
    return (
        <AppLayout
            title={`Client: ${client.name}`}
            breadcrumbs={[
                { label: 'Dashboard', href: '/dashboard' },
                { label: 'Clients', href: '/clients' },
                { label: client.name },
            ]}
        >
            <div className="space-y-6">
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
                            <p className="text-sm text-muted-foreground">Client Profile</p>
                        </div>
                    </div>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    {/* Contact Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Contact Information</CardTitle>
                            <CardDescription>Client contact details</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center gap-3">
                                <Mail className="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <p className="text-sm font-medium">Email</p>
                                    <p className="text-sm text-muted-foreground">{client.email}</p>
                                </div>
                            </div>
                            {client.phone && (
                                <div className="flex items-center gap-3">
                                    <Phone className="h-5 w-5 text-muted-foreground" />
                                    <div>
                                        <p className="text-sm font-medium">Phone</p>
                                        <p className="text-sm text-muted-foreground">{client.phone}</p>
                                    </div>
                                </div>
                            )}
                            <div className="flex items-center gap-3">
                                <Calendar className="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <p className="text-sm font-medium">Member Since</p>
                                    <p className="text-sm text-muted-foreground">
                                        {format(new Date(client.created_at), 'MMMM d, yyyy')}
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
                                Loyalty Rewards
                            </CardTitle>
                            <CardDescription>Track loyalty stamp progress</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <LoyaltyProgressBar progress={loyalty_progress} />
                        </CardContent>
                    </Card>
                </div>

                {/* Recent Appointments */}
                <Card>
                    <CardHeader>
                        <CardTitle>Recent Appointments</CardTitle>
                        <CardDescription>Last 10 appointments</CardDescription>
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
                                                    with {appointment.employee?.user?.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground mt-1">
                                                    {format(
                                                        new Date(appointment.scheduled_at),
                                                        'MMM d, yyyy • h:mm a'
                                                    )}
                                                </p>
                                            </div>
                                        </div>
                                        <div
                                            className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${
                                                appointment.status === 'completed'
                                                    ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                    : appointment.status === 'confirmed'
                                                      ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
                                                      : appointment.status === 'cancelled'
                                                        ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                                        : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200'
                                            }`}
                                        >
                                            {appointment.status}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-center py-8 text-sm text-muted-foreground">
                                No appointments yet
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
