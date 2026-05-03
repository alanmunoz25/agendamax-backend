import { EmptyState } from '@/components/empty-state';
import { FilterBar } from '@/components/payroll/filter-bar';
import { PayrollBarChart } from '@/components/payroll/payroll-bar-chart';
import { DataTable, type Column } from '@/components/data-table';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { BarChart2 } from 'lucide-react';
import { useState } from 'react';

interface ReportRow {
    service_id: number;
    service_name: string;
    commissions_count: number;
    commissions_total: string;
    avg_price: string;
    pct_of_total: number;
}

interface Summary {
    grand_total: string;
    date_from: string | null;
    date_to: string | null;
}

interface Filters {
    from: string | null;
    to: string | null;
    period_id: string | null;
}

interface PeriodOption {
    id: number;
    label: string;
}

interface Props {
    report: ReportRow[];
    summary: Summary;
    filters: Filters;
    periods_for_filter: PeriodOption[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Nómina', href: '/payroll/dashboard' },
    { title: 'Reporte por Servicio', href: '/payroll/reports/by-service' },
];

function fmt(amount: string): string {
    return Number(amount).toLocaleString('es-DO', { style: 'currency', currency: 'DOP' });
}

export default function ByService({ report, summary, filters, periods_for_filter }: Props) {
    const [filterValues, setFilterValues] = useState<Record<string, string>>({
        from: filters.from ?? '',
        to: filters.to ?? '',
        period_id: filters.period_id ?? '',
    });

    const handleFilterChange = (key: string, value: string) => {
        const updated = { ...filterValues, [key]: value };
        setFilterValues(updated);

        // Auto-apply filter when period_id is selected (clears dates)
        if (key === 'period_id' && value) {
            router.get('/payroll/reports/by-service', { period_id: value }, { preserveState: true, replace: true });
        } else if (key !== 'period_id') {
            router.get('/payroll/reports/by-service', { from: updated.from || undefined, to: updated.to || undefined }, { preserveState: true, replace: true });
        }
    };

    const handleClear = () => {
        setFilterValues({ from: '', to: '', period_id: '' });
        router.get('/payroll/reports/by-service', {}, { preserveState: true, replace: true });
    };

    const chartData = report.slice(0, 10).map((r) => ({
        label: r.service_name,
        value: Number(r.commissions_total),
    }));

    const columns: Column<ReportRow>[] = [
        {
            key: 'service_name',
            label: 'Servicio',
            render: (r) => <span className="font-medium text-foreground">{r.service_name}</span>,
        },
        {
            key: 'commissions_count',
            label: 'Comisiones',
            render: (r) => <span className="text-sm">{r.commissions_count}</span>,
        },
        {
            key: 'avg_price',
            label: 'Precio Promedio',
            render: (r) => <span className="text-sm text-muted-foreground">{fmt(r.avg_price)}</span>,
        },
        {
            key: 'commissions_total',
            label: 'Total Comisiones',
            render: (r) => <span className="font-semibold text-foreground">{fmt(r.commissions_total)}</span>,
        },
        {
            key: 'pct_of_total',
            label: '% del Total',
            render: (r) => (
                <span className="text-sm text-muted-foreground">{r.pct_of_total}%</span>
            ),
        },
    ];

    return (
        <AppLayout title="Reporte por Servicio" breadcrumbs={breadcrumbs}>
            <Head title="Reporte de Comisiones por Servicio" />
            <div className="mx-auto max-w-7xl space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Reporte por Servicio</h1>
                        <p className="text-sm text-muted-foreground">
                            Total de comisiones: {fmt(summary.grand_total)}
                        </p>
                    </div>
                </div>

                {/* Filters */}
                <FilterBar
                    filters={[
                        {
                            type: 'select',
                            key: 'period_id',
                            label: 'Período',
                            options: periods_for_filter.map((p) => ({ value: String(p.id), label: p.label })),
                        },
                        { type: 'date', key: 'from', label: 'Desde' },
                        { type: 'date', key: 'to', label: 'Hasta' },
                    ]}
                    values={filterValues}
                    onChange={handleFilterChange}
                    onClear={handleClear}
                />

                {report.length === 0 ? (
                    <EmptyState
                        icon={BarChart2}
                        title="Sin datos para este período"
                        description="No hay comisiones registradas con los filtros seleccionados."
                    />
                ) : (
                    <>
                        {/* Chart — top 10 */}
                        {chartData.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-sm font-medium text-muted-foreground">
                                        Top 10 servicios por comisiones
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <PayrollBarChart data={chartData} height={250} horizontal />
                                </CardContent>
                            </Card>
                        )}

                        {/* Table */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-sm font-medium text-muted-foreground">
                                    Detalle completo
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <DataTable
                                    data={report as unknown as Record<string, unknown>[]}
                                    columns={columns as Column<Record<string, unknown>>[]}
                                />
                                <div className="mt-4 flex justify-end border-t border-border pt-4">
                                    <p className="text-sm font-semibold text-foreground">
                                        Total: {fmt(summary.grand_total)}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
