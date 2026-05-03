import { EmptyState } from '@/components/empty-state';
import { PayrollAreaChart } from '@/components/payroll/payroll-area-chart';
import { StatCard } from '@/components/payroll/stat-card';
import { EditBaseSalaryModal } from '@/components/payroll/edit-base-salary-modal';
import { PayrollRecordStatusBadge } from '@/components/payroll-record-status-badge';
import { DataTable, type Column } from '@/components/data-table';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import * as PayrollEmployeeActions from '@/actions/App/Http/Controllers/Payroll/PayrollEmployeeController';
import { Head, router } from '@inertiajs/react';
import { ArrowLeft, Download, DollarSign, Edit, TrendingUp, Percent } from 'lucide-react';
import { useState } from 'react';

interface EmployeeInfo {
    id: number;
    name: string | null;
    role: string | null;
    is_active: boolean;
    base_salary: string | null;
}

interface Totals {
    gross_total_all_time: string;
    commissions_total: string;
    tips_total: string;
    records_count: number;
}

interface ChartPoint {
    month: string;
    gross: number;
    base: number;
}

interface RecordRow {
    record_id: number;
    period_id: number;
    period_label: string;
    starts_on: string | null;
    ends_on: string | null;
    base_salary_snapshot: string;
    commissions_total: string;
    tips_total: string;
    adjustments_total: string;
    gross_total: string;
    status: string;
}

interface PaginatedRecords {
    data: RecordRow[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

interface Props {
    employee: EmployeeInfo;
    totals: Totals;
    chart_series: ChartPoint[];
    records: PaginatedRecords;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Empleados', href: '/employees' },
    { title: 'Historial', href: '#' },
];

function fmt(amount: string): string {
    return Number(amount).toLocaleString('es-DO', { style: 'currency', currency: 'DOP' });
}

export default function Show({ employee, totals, chart_series, records }: Props) {
    const [editSalaryOpen, setEditSalaryOpen] = useState(false);

    const columns: Column<RecordRow>[] = [
        {
            key: 'period_label',
            label: 'Período',
            render: (r) => <span className="font-medium capitalize text-foreground">{r.period_label}</span>,
        },
        {
            key: 'base_salary_snapshot',
            label: 'Base',
            render: (r) => <span className="text-sm text-muted-foreground">{fmt(r.base_salary_snapshot)}</span>,
        },
        {
            key: 'commissions_total',
            label: 'Comisiones',
            render: (r) => <span className="text-sm">{fmt(r.commissions_total)}</span>,
        },
        {
            key: 'tips_total',
            label: 'Tips',
            render: (r) => <span className="text-sm">{fmt(r.tips_total)}</span>,
        },
        {
            key: 'gross_total',
            label: 'Bruto Total',
            render: (r) => <span className="font-semibold text-foreground">{fmt(r.gross_total)}</span>,
        },
        {
            key: 'status',
            label: 'Estado',
            render: (r) => <PayrollRecordStatusBadge status={r.status as 'draft' | 'approved' | 'paid' | 'voided'} />,
        },
        {
            key: 'actions',
            label: '',
            render: (r) => (
                <Button size="sm" variant="outline" onClick={() => router.visit(`/payroll/periods/${r.period_id}`)}>
                    Ver
                </Button>
            ),
        },
    ];

    return (
        <AppLayout title={`Historial — ${employee.name ?? 'Empleado'}`} breadcrumbs={breadcrumbs}>
            <Head title={`Historial de ${employee.name ?? 'Empleado'}`} />
            <div className="mx-auto max-w-7xl space-y-6">
                {/* Header */}
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="flex items-center gap-4">
                        <Button variant="outline" size="sm" onClick={() => router.visit('/employees')}>
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Volver
                        </Button>
                        <div>
                            <h1 className="text-2xl font-bold text-foreground">{employee.name}</h1>
                            <p className="text-sm text-muted-foreground">
                                {employee.base_salary ? `Salario base: ${fmt(employee.base_salary)}` : 'Sin salario base configurado'}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button variant="outline" size="sm" onClick={() => setEditSalaryOpen(true)}>
                            <Edit className="mr-2 h-4 w-4" />
                            Editar salario base
                        </Button>
                        <a
                            href={PayrollEmployeeActions.exportMethod.url(employee)}
                            download
                            className="inline-flex items-center gap-2 rounded-md border border-border bg-background px-3 py-2 text-sm font-medium text-foreground hover:bg-muted"
                        >
                            <Download className="h-4 w-4" />
                            Exportar CSV
                        </a>
                    </div>
                </div>

                {/* Stats */}
                <div className="grid gap-4 sm:grid-cols-3">
                    <StatCard icon={DollarSign} label="Bruto total acumulado" value={fmt(totals.gross_total_all_time)} />
                    <StatCard icon={Percent} label="Total comisiones" value={fmt(totals.commissions_total)} />
                    <StatCard icon={TrendingUp} label="Total tips" value={fmt(totals.tips_total)} />
                </div>

                {/* Chart */}
                {chart_series.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm font-medium text-muted-foreground">Evolución de pago</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <PayrollAreaChart data={chart_series} />
                        </CardContent>
                    </Card>
                )}

                {/* Records table */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm font-medium text-muted-foreground">
                            Historial de nómina ({totals.records_count} registros)
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <DataTable
                            data={records.data as unknown as Record<string, unknown>[]}
                            columns={columns as Column<Record<string, unknown>>[]}
                            emptyState={
                                <EmptyState
                                    icon={DollarSign}
                                    title="Sin registros de nómina"
                                    description="Este empleado aún no tiene registros de nómina generados."
                                />
                            }
                            pagination={
                                records.meta.last_page > 1
                                    ? {
                                          currentPage: records.meta.current_page,
                                          lastPage: records.meta.last_page,
                                          onPageChange: (page) =>
                                              router.visit(`/payroll/employees/${employee.id}`, {
                                                  data: { page },
                                                  preserveState: true,
                                                  replace: true,
                                              }),
                                      }
                                    : undefined
                            }
                        />
                    </CardContent>
                </Card>
            </div>

            <EditBaseSalaryModal
                open={editSalaryOpen}
                onOpenChange={setEditSalaryOpen}
                employee={employee}
                updateUrl={PayrollEmployeeActions.updateBaseSalary.url(employee)}
            />
        </AppLayout>
    );
}
