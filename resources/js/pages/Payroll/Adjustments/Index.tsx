import { EmptyState } from '@/components/empty-state';
import { FilterBar } from '@/components/payroll/filter-bar';
import { DataTable, type Column } from '@/components/data-table';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { SlidersHorizontal } from 'lucide-react';
import { useState } from 'react';

interface AdjustmentRow {
    id: number;
    type: 'credit' | 'debit';
    amount: string;
    signed_amount: string;
    reason: string;
    is_compensation: boolean;
    created_at: string | null;
    employee: { id: number; name: string | null } | null;
    period: { id: number; label: string } | null;
    origin_record_id: number | null;
}

interface AdjustmentsMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Totals {
    credits: string;
    debits: string;
    net: string;
}

interface Filters {
    employee_id: string | null;
    payroll_period_id: string | null;
    type: string | null;
    from: string | null;
    to: string | null;
}

interface EmployeeOption {
    id: number;
    name: string;
}

interface PeriodOption {
    id: number;
    label: string;
}

interface Props {
    adjustments: { data: AdjustmentRow[]; meta: AdjustmentsMeta };
    totals: Totals;
    filters: Filters;
    employees_for_filter: EmployeeOption[];
    periods_for_filter: PeriodOption[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Nómina', href: '/payroll/dashboard' },
    { title: 'Ajustes', href: '/payroll/adjustments' },
];

function fmt(amount: string): string {
    return Number(amount).toLocaleString('es-DO', { style: 'currency', currency: 'DOP' });
}

export default function Index({ adjustments, totals, filters, employees_for_filter, periods_for_filter }: Props) {
    const [filterValues, setFilterValues] = useState<Record<string, string>>({
        employee_id: filters.employee_id ?? '',
        payroll_period_id: filters.payroll_period_id ?? '',
        type: filters.type ?? '',
        from: filters.from ?? '',
        to: filters.to ?? '',
    });

    const applyFilters = (values: Record<string, string>) => {
        const params: Record<string, string | undefined> = {};
        Object.entries(values).forEach(([k, v]) => {
            if (v) { params[k] = v; }
        });
        router.get('/payroll/adjustments', params, { preserveState: true, replace: true });
    };

    const handleFilterChange = (key: string, value: string) => {
        const updated = { ...filterValues, [key]: value };
        setFilterValues(updated);
        applyFilters(updated);
    };

    const handleClear = () => {
        const cleared = { employee_id: '', payroll_period_id: '', type: '', from: '', to: '' };
        setFilterValues(cleared);
        router.get('/payroll/adjustments', {}, { preserveState: true, replace: true });
    };

    const columns: Column<AdjustmentRow>[] = [
        {
            key: 'type',
            label: 'Tipo',
            render: (a) => (
                <div className="flex items-center gap-2">
                    <Badge className={a.type === 'credit' ? 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-300'}>
                        {a.type === 'credit' ? 'Crédito' : 'Débito'}
                    </Badge>
                    {a.is_compensation && (
                        <Badge variant="outline" className="text-xs">
                            Compensación
                        </Badge>
                    )}
                </div>
            ),
        },
        {
            key: 'employee',
            label: 'Empleado',
            render: (a) => <span className="text-sm text-foreground">{a.employee?.name ?? '—'}</span>,
        },
        {
            key: 'period',
            label: 'Período',
            render: (a) => <span className="text-sm capitalize text-muted-foreground">{a.period?.label ?? '—'}</span>,
        },
        {
            key: 'reason',
            label: 'Motivo',
            render: (a) => <span className="text-sm text-muted-foreground">{a.reason}</span>,
        },
        {
            key: 'signed_amount',
            label: 'Monto',
            render: (a) => (
                <span className={`font-semibold ${a.type === 'credit' ? 'text-[var(--color-green-brand)]' : 'text-destructive'}`}>
                    {a.signed_amount}
                </span>
            ),
        },
        {
            key: 'created_at',
            label: 'Fecha',
            render: (a) => <span className="text-sm text-muted-foreground">{a.created_at ?? '—'}</span>,
        },
    ];

    return (
        <AppLayout title="Ajustes de Nómina" breadcrumbs={breadcrumbs}>
            <Head title="Ajustes de Nómina" />
            <div className="mx-auto max-w-7xl space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-foreground">Ajustes de Nómina</h1>
                    <p className="text-sm text-muted-foreground">Histórico de créditos y débitos aplicados a empleados</p>
                </div>

                {/* Totals */}
                <div className="grid gap-4 sm:grid-cols-3">
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-xs text-muted-foreground">Créditos</p>
                            <p className="text-xl font-semibold text-[var(--color-green-brand)]">+{fmt(totals.credits)}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-xs text-muted-foreground">Débitos</p>
                            <p className="text-xl font-semibold text-destructive">-{fmt(totals.debits)}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-xs text-muted-foreground">Neto</p>
                            <p className="text-xl font-semibold text-foreground">{fmt(totals.net)}</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <FilterBar
                    filters={[
                        {
                            type: 'select',
                            key: 'employee_id',
                            label: 'Empleado',
                            options: employees_for_filter.map((e) => ({ value: String(e.id), label: e.name })),
                        },
                        {
                            type: 'select',
                            key: 'payroll_period_id',
                            label: 'Período',
                            options: periods_for_filter.map((p) => ({ value: String(p.id), label: p.label })),
                        },
                        {
                            type: 'select',
                            key: 'type',
                            label: 'Tipo',
                            options: [
                                { value: 'credit', label: 'Crédito' },
                                { value: 'debit', label: 'Débito' },
                            ],
                        },
                        { type: 'date', key: 'from', label: 'Desde' },
                    ]}
                    values={filterValues}
                    onChange={handleFilterChange}
                    onClear={handleClear}
                />

                {/* Table */}
                <DataTable
                    data={adjustments.data as unknown as Record<string, unknown>[]}
                    columns={columns as Column<Record<string, unknown>>[]}
                    emptyState={
                        <EmptyState
                            icon={SlidersHorizontal}
                            title="Sin ajustes registrados"
                            description="No hay ajustes que coincidan con los filtros seleccionados."
                        />
                    }
                    pagination={
                        adjustments.meta.last_page > 1
                            ? {
                                  currentPage: adjustments.meta.current_page,
                                  lastPage: adjustments.meta.last_page,
                                  onPageChange: (page) =>
                                      router.get('/payroll/adjustments', { ...filterValues, page: String(page) }, { preserveState: true, replace: true }),
                              }
                            : undefined
                    }
                />
            </div>
        </AppLayout>
    );
}
