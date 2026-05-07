import { Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Calendar, Clock, MapPin, User } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

interface AppointmentService {
    id: number;
    name: string;
    price: number | null;
}

interface AppointmentItem {
    id: number;
    scheduled_at: string | null;
    scheduled_until: string | null;
    status: string;
    notes: string | null;
    final_price: string | null;
    service: {
        id: number;
        name: string;
        price: number | null;
        duration: number;
    } | null;
    employee: {
        id: number;
        name: string | null;
    } | null;
    services: AppointmentService[];
}

interface BusinessGroup {
    business: {
        id: number;
        name: string;
        slug: string;
        logo_url: string | null;
    } | null;
    appointments: AppointmentItem[];
    total_count: number;
    is_blocked: boolean;
}

interface Props {
    appointments_grouped: BusinessGroup[];
}

type TabKey = 'upcoming' | 'past' | 'cancelled';

function getInitials(name: string): string {
    return name
        .split(' ')
        .slice(0, 2)
        .map((n) => n[0])
        .join('')
        .toUpperCase();
}

function formatDateTime(iso: string | null): string {
    if (!iso) {
        return '—';
    }
    return new Intl.DateTimeFormat('es-DO', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(iso));
}

function getStatusVariant(
    status: string,
): 'default' | 'secondary' | 'outline' | 'destructive' {
    switch (status) {
        case 'confirmed':
            return 'default';
        case 'completed':
            return 'secondary';
        case 'cancelled':
            return 'destructive';
        default:
            return 'outline';
    }
}

function classifyAppointment(appt: AppointmentItem): TabKey {
    if (appt.status === 'cancelled') {
        return 'cancelled';
    }
    const now = new Date();
    const scheduledAt = appt.scheduled_at ? new Date(appt.scheduled_at) : null;

    if (appt.status === 'completed') {
        return 'past';
    }
    if (scheduledAt && scheduledAt < now && appt.status !== 'cancelled') {
        return 'past';
    }
    return 'upcoming';
}

export default function MyAppointments({ appointments_grouped }: Props) {
    const { t } = useTranslation();
    const [activeTab, setActiveTab] = useState<TabKey>('upcoming');

    const tabs: { key: TabKey; label: string }[] = [
        { key: 'upcoming', label: t('client.appointments.tab_upcoming') },
        { key: 'past', label: t('client.appointments.tab_past') },
        { key: 'cancelled', label: t('client.appointments.tab_cancelled') },
    ];

    const filteredGroups: BusinessGroup[] = appointments_grouped
        .map((group) => ({
            ...group,
            appointments: group.appointments.filter(
                (appt) => classifyAppointment(appt) === activeTab,
            ),
        }))
        .filter((group) => group.appointments.length > 0);

    const totalAppointments = appointments_grouped.reduce(
        (sum, g) => sum + g.total_count,
        0,
    );

    const getStatusLabel = (status: string): string => {
        const key = `client.appointments.status.${status}`;
        const translated = t(key);
        return translated !== key ? translated : status;
    };

    return (
        <>
            <Head title={t('client.appointments.title')} />

            <div className="min-h-screen bg-background text-foreground">
                {/* Header */}
                <header className="sticky top-0 z-50 border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
                    <div className="mx-auto flex max-w-4xl items-center justify-between px-4 py-3">
                        <div className="flex items-center gap-3">
                            <Link href="/buscar" className="text-sm text-muted-foreground hover:text-foreground">
                                AgendaMax
                            </Link>
                            <span className="text-muted-foreground">/</span>
                            <span className="text-sm font-medium">{t('client.appointments.title')}</span>
                        </div>
                    </div>
                </header>

                <main className="mx-auto max-w-4xl px-4 py-8">
                    <h1 className="mb-6 text-2xl font-bold tracking-tight">
                        {t('client.appointments.title')}
                    </h1>

                    {/* Tabs */}
                    <div className="mb-6 inline-flex gap-1 rounded-lg bg-muted/50 p-1">
                        {tabs.map(({ key, label }) => (
                            <button
                                key={key}
                                onClick={() => setActiveTab(key)}
                                className={[
                                    'rounded-md px-4 py-1.5 text-sm font-medium transition-colors',
                                    activeTab === key
                                        ? 'bg-background text-foreground shadow-xs'
                                        : 'text-muted-foreground hover:text-foreground',
                                ].join(' ')}
                            >
                                {label}
                            </button>
                        ))}
                    </div>

                    {/* Empty state — no appointments at all */}
                    {totalAppointments === 0 ? (
                        <div className="flex flex-col items-center gap-3 py-20 text-center">
                            <Calendar className="size-12 text-muted-foreground/50" />
                            <p className="text-lg font-semibold">
                                {t('client.appointments.empty_title')}
                            </p>
                            <Link href="/buscar">
                                <Button variant="outline">
                                    <MapPin className="mr-2 size-4" />
                                    {t('client.appointments.empty_action')}
                                </Button>
                            </Link>
                        </div>
                    ) : filteredGroups.length === 0 ? (
                        /* Empty state — no appointments in this tab */
                        <div className="py-16 text-center text-muted-foreground">
                            <Calendar className="mx-auto mb-3 size-10 opacity-40" />
                            <p className="text-sm">{t('client.appointments.empty_title')}</p>
                        </div>
                    ) : (
                        <div className="space-y-6">
                            {filteredGroups.map((group, groupIdx) => (
                                <Card key={group.business?.id ?? groupIdx}>
                                    {/* Business Header */}
                                    <CardHeader className="pb-3">
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="flex items-center gap-3">
                                                {group.business?.logo_url ? (
                                                    <img
                                                        src={group.business.logo_url}
                                                        alt={group.business.name}
                                                        className="h-10 w-10 shrink-0 rounded-lg object-cover"
                                                    />
                                                ) : (
                                                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-sm font-bold text-primary">
                                                        {getInitials(group.business?.name ?? '?')}
                                                    </div>
                                                )}
                                                <div>
                                                    <p className="font-semibold leading-tight">
                                                        {group.business?.name ?? t('common.unknown')}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {group.appointments.length}{' '}
                                                        {group.appointments.length === 1
                                                            ? 'cita'
                                                            : 'citas'}
                                                    </p>
                                                </div>
                                            </div>

                                            {group.is_blocked && (
                                                <Badge variant="outline" className="border-amber-500 text-amber-600 dark:text-amber-400">
                                                    {t('client.appointments.blocked_badge')}
                                                </Badge>
                                            )}
                                        </div>
                                    </CardHeader>

                                    <CardContent className="divide-y">
                                        {group.appointments.map((appt) => {
                                            const serviceName =
                                                appt.services.length > 0
                                                    ? appt.services.map((s) => s.name).join(', ')
                                                    : appt.service?.name ?? '—';

                                            const price =
                                                appt.final_price != null
                                                    ? `RD$ ${Number(appt.final_price).toLocaleString('es-DO', { minimumFractionDigits: 0 })}`
                                                    : appt.service?.price != null
                                                      ? `RD$ ${Number(appt.service.price).toLocaleString('es-DO', { minimumFractionDigits: 0 })}`
                                                      : null;

                                            return (
                                                <div
                                                    key={appt.id}
                                                    className="flex flex-col gap-2 py-3 first:pt-0 sm:flex-row sm:items-start sm:justify-between"
                                                >
                                                    <div className="flex flex-col gap-1.5">
                                                        {/* Date / Time */}
                                                        <div className="flex items-center gap-1.5 text-sm font-medium">
                                                            <Clock className="size-3.5 shrink-0 text-muted-foreground" />
                                                            {formatDateTime(appt.scheduled_at)}
                                                        </div>

                                                        {/* Service */}
                                                        <p className="text-sm text-foreground">
                                                            {serviceName}
                                                        </p>

                                                        {/* Employee */}
                                                        {appt.employee?.name && (
                                                            <div className="flex items-center gap-1 text-xs text-muted-foreground">
                                                                <User className="size-3 shrink-0" />
                                                                {appt.employee.name}
                                                            </div>
                                                        )}

                                                        {/* Price */}
                                                        {price && (
                                                            <p className="text-xs font-medium text-muted-foreground">
                                                                {price}
                                                            </p>
                                                        )}
                                                    </div>

                                                    {/* Status + Action */}
                                                    <div className="flex items-center gap-2 sm:flex-col sm:items-end">
                                                        <Badge
                                                            variant={getStatusVariant(appt.status)}
                                                        >
                                                            {getStatusLabel(appt.status)}
                                                        </Badge>
                                                        {group.business?.slug && (
                                                            <Link
                                                                href={`/negocio/${group.business.slug}`}
                                                            >
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    className="text-xs"
                                                                >
                                                                    {t(
                                                                        'client.appointments.view_details',
                                                                    )}
                                                                </Button>
                                                            </Link>
                                                        )}
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    )}
                </main>
            </div>
        </>
    );
}
