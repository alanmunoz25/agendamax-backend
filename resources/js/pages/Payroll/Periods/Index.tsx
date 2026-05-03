import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { DataTable, type Column } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { ConfirmationModal } from '@/components/confirmation-modal';
import { CreatePeriodModal } from '@/components/payroll/create-period-modal';
import { PayrollPeriodStatusBadge } from '@/components/payroll-period-status-badge';
import {
    index as periodIndex,
    store as periodStore,
    generate as periodGenerate,
    show as periodShow,
} from '@/actions/App/Http/Controllers/Payroll/PayrollPeriodController';
import type { PayrollPeriod } from '@/types/models';
import type { PaginatedData } from '@/types/pagination';
import type { BreadcrumbItem } from '@/types';
import { CheckCircle, Clock, DollarSign, Plus, Users } from 'lucide-react';

interface Summary {
    open_count: number;
    pending_payment_count: number;
    total_pending: string;
    active_employees: number;
}

interface Filters {
    search?: string;
    status?: string;
    year_month?: string;
}

interface Props {
    periods: PaginatedData<PayrollPeriod & { records_count: number; paid_count: number }>;
    summary: Summary;
    filters: Filters;
}

type PeriodRow = PayrollPeriod & { records_count: number; paid_count: number };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Períodos de Nómina', href: '/payroll/periods' },
];

function periodLabel(startsOn: string): string {
    const [year, month] = startsOn.split('-').map(Number);
    return new Date(year, month - 1, 1).toLocaleDateString('es-MX', {
        month: 'long',
        year: 'numeric',
    });
}

function formatCurrency(amount: string): string {
    return Number(amount).toLocaleString('es-MX', {
        style: 'currency',
        currency: 'MXN',
    });
}

export default function Index({ periods, summary, filters }: Props) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const [createModalOpen, setCreateModalOpen] = useState(false);
    const [generatePeriod, setGeneratePeriod] = useState<PeriodRow | null>(null);
    const [processing, setProcessing] = useState(false);

    const hasFilters = !!(filters.search || filters.status || filters.year_month);

    const handleSearch = (value: string) => {
        router.get(
            periodIndex.url(),
            { ...filters, search: value || undefined },
            { preserveState: true, replace: true },
        );
    };

    const handleStatusFilter = (value: string) => {
        router.get(
            periodIndex.url(),
            { ...filters, status: value === 'all' ? undefined : value },
            { preserveState: true, replace: true },
        );
    };

    const handleYearMonthFilter = (value: string) => {
        router.get(
            periodIndex.url(),
            { ...filters, year_month: value || undefined },
            { preserveState: true, replace: true },
        );
    };

    const handleClearFilters = () => {
        router.get(periodIndex.url(), {}, { preserveState: true, replace: true });
    };

    const handleCreatePeriod = (start: string, end: string) => {
        setProcessing(true);
        router.post(
            periodStore.url(),
            { start, end },
            {
                onSuccess: () => {
                    setCreateModalOpen(false);
                    setProcessing(false);
                },
                onError: () => setProcessing(false),
            },
        );
    };

    const handleGenerate = () => {
        if (!generatePeriod) return;
        router.post(periodGenerate.url(generatePeriod), {}, {
            onFinish: () => setGeneratePeriod(null),
        });
    };

    const columns: Column<PeriodRow>[] = [
        {
            key: 'period',
            label: 'Período',
            render: (p) => (
                <div className="font-medium capitalize text-foreground">
                    {periodLabel(p.starts_on)}
                </div>
            ),
        },
        {
            key: 'range',
            label: 'Rango',
            render: (p) => (
                <div className="text-sm text-muted-foreground whitespace-nowrap">
                    {p.starts_on} — {p.ends_on}
                </div>
            ),
        },
        {
            key: 'status',
            label: 'Estado',
            render: (p) => <PayrollPeriodStatusBadge status={p.status} />,
        },
        {
            key: 'records',
            label: 'Empleados',
            render: (p) => (
                <div className="text-sm text-muted-foreground">
                    {p.records_count} records · {p.paid_count} pagados
                </div>
            ),
        },
        {
            key: 'actions',
            label: '',
            render: (p) => (
                <div className="flex items-center justify-end gap-2">
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() => router.get(periodShow.url(p))}
                    >
                        Ver
                    </Button>
                    {p.status === 'open' && p.records_count === 0 && (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => setGeneratePeriod(p)}
                        >
                            Generar
                        </Button>
                    )}
                </div>
            ),
        },
    ];

    return (
        <AppLayout title="Períodos de Nómina" breadcrumbs={breadcrumbs}>
            <Head title="Períodos de Nómina" />

            <div className="mx-auto max-w-7xl space-y-6">
                {/* Page header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Períodos de Nómina</h1>
                        <p className="text-sm text-muted-foreground">
                            Gestiona y aprueba los ciclos de pago de tus empleados
                        </p>
                    </div>
                    <Button onClick={() => setCreateModalOpen(true)}>
                        <Plus className="mr-2 h-4 w-4" />
                        Crear Período
                    </Button>
                </div>

                {/* Summary cards */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/20">
                                    <Clock className="h-5 w-5 text-[var(--color-blue-brand)]" />
                                </div>
                                <div>
                                    <p className="text-2xl font-bold text-foreground">
                                        {summary.open_count}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Períodos abiertos
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/20">
                                    <CheckCircle className="h-5 w-5 text-[var(--color-amber-brand)]" />
                                </div>
                                <div>
                                    <p className="text-2xl font-bold text-foreground">
                                        {summary.pending_payment_count}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Aprobados pendientes
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/20">
                                    <DollarSign className="h-5 w-5 text-[var(--color-green-brand)]" />
                                </div>
                                <div>
                                    <p className="text-2xl font-bold text-foreground">
                                        {formatCurrency(summary.total_pending)}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Total pendiente de pago
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-muted">
                                    <Users className="h-5 w-5 text-muted-foreground" />
                                </div>
                                <div>
                                    <p className="text-2xl font-bold text-foreground">
                                        {summary.active_employees}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Empleados activos
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <div className="rounded-lg border border-border bg-card p-4">
                    <div className="flex flex-wrap items-center gap-3">
                        <Input
                            placeholder="Buscar período..."
                            defaultValue={filters.search ?? ''}
                            onChange={(e) => handleSearch(e.target.value)}
                            className="min-w-[180px] grow"
                        />
                        <Select
                            value={filters.status ?? 'all'}
                            onValueChange={handleStatusFilter}
                        >
                            <SelectTrigger className="w-[140px]">
                                <SelectValue placeholder="Estado" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todos</SelectItem>
                                <SelectItem value="open">Abierto</SelectItem>
                                <SelectItem value="closed">Cerrado</SelectItem>
                            </SelectContent>
                        </Select>
                        <Input
                            type="month"
                            value={filters.year_month ?? ''}
                            onChange={(e) => handleYearMonthFilter(e.target.value)}
                            className="w-[160px] cursor-pointer"
                        />
                        {hasFilters && (
                            <Button variant="ghost" size="sm" onClick={handleClearFilters}>
                                Limpiar filtros
                            </Button>
                        )}
                    </div>
                </div>

                {/* Data table */}
                <DataTable
                    data={periods.data}
                    columns={columns}
                    emptyState={
                        <EmptyState
                            icon={DollarSign}
                            title="Aún no hay períodos de nómina"
                            description="Crea el primer período para comenzar a gestionar los pagos."
                            action={{
                                label: '+ Crear Primer Período',
                                onClick: () => setCreateModalOpen(true),
                            }}
                        />
                    }
                    pagination={
                        periods.last_page > 1
                            ? {
                                  currentPage: periods.current_page,
                                  lastPage: periods.last_page,
                                  onPageChange: (page) =>
                                      router.get(
                                          periodIndex.url(),
                                          { ...filters, page },
                                          { preserveState: true, replace: true },
                                      ),
                              }
                            : undefined
                    }
                />
            </div>

            <CreatePeriodModal
                open={createModalOpen}
                onOpenChange={(open) => {
                    setCreateModalOpen(open);
                    if (!open) setProcessing(false);
                }}
                onConfirm={handleCreatePeriod}
                processing={processing}
                errors={{ start: errors?.start, end: errors?.end }}
            />

            <ConfirmationModal
                open={!!generatePeriod}
                onOpenChange={(open) => {
                    if (!open) setGeneratePeriod(null);
                }}
                title="Generar Records de Nómina"
                description="¿Generar records para todos los empleados activos en este período? Esta acción no se puede deshacer."
                confirmLabel="Generar"
                cancelLabel="Cancelar"
                onConfirm={handleGenerate}
            />
        </AppLayout>
    );
}
