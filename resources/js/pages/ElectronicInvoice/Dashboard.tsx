import AppLayout from '@/layouts/app-layout';
import { CertificateExpiryAlert } from '@/components/electronic-invoice/certificate-expiry-alert';
import { DgiiStatusBadge } from '@/components/electronic-invoice/dgii-status-badge';
import { EnvironmentBanner } from '@/components/electronic-invoice/environment-banner';
import { NcfFormatter } from '@/components/electronic-invoice/ncf-formatter';
import { StatCard } from '@/components/payroll/stat-card';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    FileText,
    CheckCircle,
    Loader,
    XCircle,
    Plus,
} from 'lucide-react';

interface Alert {
    type: 'cert_expired' | 'cert_expiring' | 'low_sequence';
    message?: string;
    days_remaining?: number;
    expires_at?: string;
    expired_at?: string;
    tipo?: string;
    available?: number;
}

interface RecentEcf {
    id: number;
    numero_ecf: string;
    tipo: string;
    rnc_comprador: string | null;
    razon_social_comprador: string | null;
    monto_total: string;
    status: string;
    fecha_emision: string | null;
}

interface Kpis {
    ecfs_today: number;
    accepted_this_month: number;
    in_process: number;
    rejected_this_month: number;
}

interface Props {
    config: { ambiente: string; activo: boolean; cert_expiry: string | null } | null;
    kpis: Kpis | null;
    alerts: Alert[];
    recent_ecfs: RecentEcf[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Facturación Electrónica', href: '/admin/electronic-invoice/dashboard' },
];

function fmtDOP(amount: string): string {
    return Number(amount).toLocaleString('es-DO', {
        style: 'currency',
        currency: 'DOP',
        minimumFractionDigits: 0,
    });
}

export default function ElectronicInvoiceDashboard({
    config,
    kpis,
    alerts,
    recent_ecfs,
}: Props) {
    if (!config || !config.activo) {
        return (
            <AppLayout
                title="Facturación Electrónica"
                breadcrumbs={breadcrumbs}
            >
                <Head title="Facturación Electrónica — Dashboard" />
                <div className="mx-auto max-w-7xl">
                    <EmptyState
                        icon={FileText}
                        title="Bienvenido a Facturación Electrónica de AgendaMax"
                        description="Emite comprobantes fiscales electrónicos (e-CF) según normativa DGII de República Dominicana. Para comenzar, configura los datos de tu empresa."
                        action={{
                            label: 'Comenzar Configuración →',
                            onClick: () =>
                                router.visit(
                                    '/admin/electronic-invoice/settings'
                                ),
                        }}
                    />
                </div>
            </AppLayout>
        );
    }

    const certExpiryAlert = alerts.find(
        (a) => a.type === 'cert_expired' || a.type === 'cert_expiring'
    );
    const certExpired = certExpiryAlert?.type === 'cert_expired';

    return (
        <AppLayout
            title="Facturación Electrónica — Dashboard"
            breadcrumbs={breadcrumbs}
        >
            <Head title="Facturación Electrónica — Dashboard" />
            <div className="mx-auto max-w-7xl space-y-6">
                <EnvironmentBanner ambiente={config.ambiente} />

                {certExpiryAlert && (
                    <CertificateExpiryAlert
                        expiresAt={
                            certExpiryAlert.expires_at ??
                            certExpiryAlert.expired_at ??
                            null
                        }
                        daysRemaining={
                            certExpired ? 0 : certExpiryAlert.days_remaining
                        }
                    />
                )}

                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight text-foreground">
                            Facturación Electrónica
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            República Dominicana — DGII
                        </p>
                    </div>
                    <Button
                        onClick={() =>
                            router.visit('/admin/electronic-invoice/issued/create')
                        }
                        disabled={certExpired}
                        title={
                            certExpired
                                ? 'Certificado expirado'
                                : undefined
                        }
                    >
                        <Plus className="mr-2 h-4 w-4" />
                        Emitir e-CF
                    </Button>
                </div>

                {/* KPI Cards */}
                {kpis && (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <StatCard
                            icon={FileText}
                            label="e-CFs hoy"
                            value={String(kpis.ecfs_today)}
                        />
                        <StatCard
                            icon={CheckCircle}
                            label="Aceptados este mes"
                            value={String(kpis.accepted_this_month)}
                        />
                        <StatCard
                            icon={Loader}
                            label="En proceso"
                            value={String(kpis.in_process)}
                        />
                        <StatCard
                            icon={XCircle}
                            label="Rechazados este mes"
                            value={String(kpis.rejected_this_month)}
                            trendPositive={kpis.rejected_this_month === 0}
                        />
                    </div>
                )}

                {/* Sequence Alerts */}
                {alerts.filter((a) => a.type === 'low_sequence').length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <AlertTriangle className="h-4 w-4 text-amber-500" />
                                Alertas de Secuencias
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {alerts
                                .filter((a) => a.type === 'low_sequence')
                                .map((alert, i) => (
                                    <div
                                        key={i}
                                        className="flex items-center justify-between rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm dark:border-amber-800 dark:bg-amber-950/30"
                                    >
                                        <span className="text-amber-800 dark:text-amber-300">
                                            <AlertTriangle className="mr-1 inline h-3.5 w-3.5" />
                                            Tipo {alert.tipo}:{' '}
                                            <strong>
                                                {alert.available} secuencias
                                                restantes
                                            </strong>
                                        </span>
                                        <Link
                                            href="/admin/electronic-invoice/settings"
                                            className="text-xs font-medium text-amber-700 underline dark:text-amber-400"
                                        >
                                            Añadir secuencias →
                                        </Link>
                                    </div>
                                ))}
                        </CardContent>
                    </Card>
                )}

                {/* Recent ECFs */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle className="text-base">
                            Últimas 10 emisiones
                        </CardTitle>
                        <Link
                            href="/admin/electronic-invoice/issued"
                            className="text-sm text-primary hover:underline"
                        >
                            Ver todos los e-CFs →
                        </Link>
                    </CardHeader>
                    <CardContent>
                        {recent_ecfs.length === 0 ? (
                            <p className="text-center text-sm text-muted-foreground">
                                Sin emisiones aún.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b border-border text-left text-xs text-muted-foreground">
                                            <th className="pb-2 pr-4 font-medium">
                                                eNCF
                                            </th>
                                            <th className="pb-2 pr-4 font-medium">
                                                Tipo
                                            </th>
                                            <th className="pb-2 pr-4 font-medium">
                                                Cliente
                                            </th>
                                            <th className="pb-2 pr-4 font-medium">
                                                Monto
                                            </th>
                                            <th className="pb-2 font-medium">
                                                Status
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {recent_ecfs.map((ecf) => (
                                            <tr
                                                key={ecf.id}
                                                className="border-b border-border/50 last:border-0"
                                            >
                                                <td className="py-2 pr-4">
                                                    <Link
                                                        href={`/admin/electronic-invoice/issued/${ecf.id}`}
                                                        className="font-mono text-xs text-primary hover:underline"
                                                    >
                                                        <NcfFormatter
                                                            ncf={
                                                                ecf.numero_ecf
                                                            }
                                                        />
                                                    </Link>
                                                </td>
                                                <td className="py-2 pr-4 text-muted-foreground">
                                                    {ecf.tipo}
                                                </td>
                                                <td className="py-2 pr-4">
                                                    {ecf.razon_social_comprador ??
                                                        ecf.rnc_comprador ??
                                                        'Cliente General'}
                                                </td>
                                                <td className="py-2 pr-4 font-medium">
                                                    {fmtDOP(ecf.monto_total)}
                                                </td>
                                                <td className="py-2">
                                                    <DgiiStatusBadge
                                                        status={ecf.status}
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
