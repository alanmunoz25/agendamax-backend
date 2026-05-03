import { EmptyState } from '@/components/empty-state';
import { PayrollBarChart } from '@/components/payroll/payroll-bar-chart';
import { StatCard } from '@/components/payroll/stat-card';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, router, WhenVisible } from '@inertiajs/react';
import { BarChart2, DollarSign, TrendingUp, Users } from 'lucide-react';

interface Kpis {
    total_paid_this_year: string;
    current_period_total: string;
    current_period_status: string | null;
    current_period_label: string | null;
    active_employees_count: number;
    has_periods: boolean;
}

interface MonthlySeries {
    month: string;
    label: string;
    total: string;
}

interface EmployeeDistribution {
    employee_id: number;
    name: string;
    total: string;
    pct: number;
}

interface RecentPaid {
    record_id: number;
    employee_name: string;
    gross_total: string;
    period_label: string;
    paid_at: string | null;
    period_id: number;
}

interface Props {
    kpis: Kpis;
    monthly_series: MonthlySeries[];
    employee_distribution: EmployeeDistribution[];
    recent_paid: RecentPaid[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Nómina', href: '/payroll/dashboard' },
];

function fmt(amount: string): string {
    return Number(amount).toLocaleString('es-DO', { style: 'currency', currency: 'DOP' });
}

export default function Dashboard({ kpis, monthly_series, employee_distribution, recent_paid }: Props) {
    if (!kpis.has_periods) {
        return (
            <AppLayout title="Nómina — Dashboard" breadcrumbs={breadcrumbs}>
                <Head title="Dashboard de Nómina" />
                <div className="mx-auto max-w-7xl">
                    <EmptyState
                        icon={BarChart2}
                        title="Bienvenido al módulo de Nómina"
                        description="Gestiona los pagos de tus empleados con comisiones automáticas, tips y ajustes."
                        action={{ label: 'Ir a Períodos', onClick: () => router.visit('/payroll/periods') }}
                    />
                </div>
            </AppLayout>
        );
    }

    const chartData = (monthly_series ?? []).map((s) => ({ label: s.label, value: Number(s.total) }));
    const distData = (employee_distribution ?? []).map((e) => ({ label: e.name, value: Number(e.total) }));

    return (
        <AppLayout title="Nómina — Dashboard" breadcrumbs={breadcrumbs}>
            <Head title="Dashboard de Nómina" />
            <div className="mx-auto max-w-7xl space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Resumen de Nómina</h1>
                        {kpis.current_period_label && (
                            <p className="text-sm text-muted-foreground">
                                Período actual: {kpis.current_period_label}
                                {kpis.current_period_status === 'open' && (
                                    <Badge variant="outline" className="ml-2">
                                        En curso
                                    </Badge>
                                )}
                            </p>
                        )}
                    </div>
                </div>

                {/* KPI Cards */}
                <div className="grid gap-4 sm:grid-cols-3">
                    <StatCard icon={DollarSign} label="Total pagado (año actual)" value={fmt(kpis.total_paid_this_year)} />
                    <StatCard
                        icon={TrendingUp}
                        label="Período actual (provisional)"
                        value={fmt(kpis.current_period_total)}
                        badge={
                            kpis.current_period_status === 'open' ? (
                                <Badge variant="outline" className="text-xs">
                                    Provisional
                                </Badge>
                            ) : undefined
                        }
                    />
                    <StatCard icon={Users} label="Empleados activos" value={String(kpis.active_employees_count)} />
                </div>

                {/* Monthly evolution chart */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm font-medium text-muted-foreground">
                            Evolución mensual (últimos 12 meses)
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <WhenVisible data="monthly_series" fallback={<Skeleton className="h-64 w-full rounded-lg" />}>
                            {chartData.length > 0 ? (
                                <PayrollBarChart data={chartData} height={260} />
                            ) : (
                                <div className="flex h-64 items-center justify-center text-sm text-muted-foreground">
                                    Sin períodos cerrados aún
                                </div>
                            )}
                        </WhenVisible>
                    </CardContent>
                </Card>

                {/* Employee distribution */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm font-medium text-muted-foreground">
                            Distribución por empleado (último período cerrado)
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <WhenVisible data="employee_distribution" fallback={<Skeleton className="h-48 w-full rounded-lg" />}>
                            {distData.length > 0 ? (
                                <PayrollBarChart data={distData} height={220} horizontal />
                            ) : (
                                <div className="flex h-48 items-center justify-center text-sm text-muted-foreground">
                                    Sin datos de distribución
                                </div>
                            )}
                        </WhenVisible>
                    </CardContent>
                </Card>

                {/* Recent paid */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm font-medium text-muted-foreground">Actividad reciente</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <WhenVisible
                            data="recent_paid"
                            fallback={
                                <div className="space-y-2">
                                    {[...Array(5)].map((_, i) => (
                                        <Skeleton key={i} className="h-8 w-full rounded" />
                                    ))}
                                </div>
                            }
                        >
                            {(recent_paid ?? []).length > 0 ? (
                                <div className="space-y-2">
                                    {(recent_paid ?? []).map((r) => (
                                        <div
                                            key={r.record_id}
                                            className="flex items-center justify-between rounded-lg border border-border p-3 text-sm"
                                        >
                                            <span className="font-medium text-foreground">{r.employee_name}</span>
                                            <span className="text-muted-foreground">{fmt(r.gross_total)}</span>
                                            <span className="text-muted-foreground">{r.period_label}</span>
                                            <button
                                                onClick={() => router.visit(`/payroll/periods/${r.period_id}`)}
                                                className="text-[var(--color-blue-brand)] hover:underline"
                                            >
                                                Ver
                                            </button>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-sm text-muted-foreground">Sin actividad reciente</p>
                            )}
                        </WhenVisible>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
