import { useState, useEffect } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { EmptyState } from '@/components/empty-state';
import { ConfirmationModal } from '@/components/confirmation-modal';
import { MarkPaidModal } from '@/components/payroll/mark-paid-modal';
import { VoidConfirmationModal } from '@/components/payroll/void-confirmation-modal';
import { AddAdjustmentModal, type AdjustmentFormData } from '@/components/payroll/add-adjustment-modal';
import { PayrollPeriodStatusBadge } from '@/components/payroll-period-status-badge';
import { PayrollRecordStatusBadge } from '@/components/payroll-record-status-badge';
import { NegativeGrossBadge } from '@/components/negative-gross-badge';
import {
    generate as periodGenerate,
    approve as periodApprove,
    show as periodShow,
    employee as periodEmployee,
    exportMethod as periodExport,
} from '@/actions/App/Http/Controllers/Payroll/PayrollPeriodController';
import {
    markPaid as recordMarkPaid,
    voidMethod as recordVoid,
} from '@/actions/App/Http/Controllers/Payroll/PayrollRecordController';
import { store as adjustmentStore } from '@/actions/App/Http/Controllers/Payroll/PayrollAdjustmentController';
import type { Employee, PayrollPeriod, PayrollRecord, CommissionRecord, Tip, PayrollAdjustment, User } from '@/types/models';
import type { BreadcrumbItem } from '@/types';
import {
    AlertTriangle,
    ArrowLeft,
    ChevronDown,
    ChevronRight,
    Download,
    Info,
    Users,
} from 'lucide-react';

type EnrichedRecord = PayrollRecord & {
    employee: Employee & { user: User };
    commissions: CommissionRecord[];
    tips: Tip[];
    adjustments: PayrollAdjustment[];
};

interface PeriodSummary {
    total_gross: string;
    draft_count: number;
    approved_count: number;
    paid_count: number;
    voided_count: number;
}

interface NextOpenPeriod {
    id: number;
    starts_on: string;
    ends_on: string;
}

interface Can {
    generate: boolean;
    approve_all: boolean;
    add_adjustment: boolean;
}

interface Props {
    period: PayrollPeriod & { has_records: boolean };
    next_open_period: NextOpenPeriod | null;
    records: EnrichedRecord[];
    employees: Employee[];
    period_summary: PeriodSummary;
    can: Can;
    pending_commissions_count: number;
}

function periodLabel(startsOn: string): string {
    const [year, month] = startsOn.split('-').map(Number);
    return new Date(year, month - 1, 1).toLocaleDateString('es-MX', {
        month: 'long',
        year: 'numeric',
    });
}

function formatCurrency(amount: string | number): string {
    return Number(amount).toLocaleString('es-MX', {
        style: 'currency',
        currency: 'MXN',
    });
}

function ruleLabel(ruleType: string, ruleValue: string): string {
    if (ruleType === 'percentage') return `${ruleValue}%`;
    if (ruleType === 'fixed') return formatCurrency(ruleValue);
    return ruleValue;
}

export default function Show({
    period,
    next_open_period,
    records,
    employees,
    period_summary,
    can,
    pending_commissions_count,
}: Props) {
    const [expandedRows, setExpandedRows] = useState<Set<number>>(new Set());
    const [markPaidRecord, setMarkPaidRecord] = useState<EnrichedRecord | null>(null);
    const [voidRecord, setVoidRecord] = useState<EnrichedRecord | null>(null);
    const [approveAllOpen, setApproveAllOpen] = useState(false);
    const [addAdjustmentOpen, setAddAdjustmentOpen] = useState(false);
    const [addAdjustmentPreselect, setAddAdjustmentPreselect] = useState<Employee | undefined>();
    const [processingId, setProcessingId] = useState<number | null>(null);

    const label = periodLabel(period.starts_on);
    const isClosed = period.status === 'closed';

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Períodos de Nómina', href: '/payroll/periods' },
        { title: label, href: periodShow.url(period) },
    ];

    useEffect(() => {
        if (isClosed) return;
        const id = setInterval(() => {
            router.reload({ only: ['period', 'records', 'can', 'period_summary', 'next_open_period'] });
        }, 30000);
        return () => clearInterval(id);
    }, [isClosed]);

    const toggleRow = (recordId: number) => {
        setExpandedRows((prev) => {
            const next = new Set(prev);
            if (next.has(recordId)) {
                next.delete(recordId);
            } else {
                next.add(recordId);
            }
            return next;
        });
    };

    const handleApproveAll = () => {
        router.post(periodApprove.url(period), {}, {
            onFinish: () => setApproveAllOpen(false),
        });
    };

    const handleMarkPaid = (paymentMethod: string, paymentReference: string) => {
        if (!markPaidRecord) return;
        setProcessingId(markPaidRecord.id);
        router.post(
            recordMarkPaid.url(markPaidRecord),
            { payment_method: paymentMethod, payment_reference: paymentReference },
            {
                onSuccess: () => setMarkPaidRecord(null),
                onFinish: () => setProcessingId(null),
            },
        );
    };

    const handleVoid = (reason: string) => {
        if (!voidRecord) return;
        setProcessingId(voidRecord.id);
        router.post(
            recordVoid.url(voidRecord),
            { reason },
            {
                onSuccess: () => setVoidRecord(null),
                onFinish: () => setProcessingId(null),
            },
        );
    };

    const handleAddAdjustment = (data: AdjustmentFormData) => {
        router.post(adjustmentStore.url(period), data, {
            onSuccess: () => {
                setAddAdjustmentOpen(false);
                setAddAdjustmentPreselect(undefined);
            },
        });
    };

    const openAddAdjustment = (preselect?: Employee) => {
        setAddAdjustmentPreselect(preselect);
        setAddAdjustmentOpen(true);
    };

    return (
        <AppLayout title={`Período: ${label}`} breadcrumbs={breadcrumbs}>
            <Head title={`Período: ${label}`} />

            <div className="mx-auto max-w-7xl space-y-6">
                {/* Back + header */}
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-1">
                        <Link
                            href="/payroll/periods"
                            className="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            Volver a períodos
                        </Link>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-bold capitalize text-foreground">
                                Período: {label}
                            </h1>
                            <PayrollPeriodStatusBadge status={period.status} />
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {period.starts_on} — {period.ends_on}
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {!isClosed && can.generate && (
                            <Button
                                variant="outline"
                                onClick={() => router.post(periodGenerate.url(period), {})}
                            >
                                Generar Records
                            </Button>
                        )}
                        {!isClosed && can.approve_all && (
                            <Button
                                variant="outline"
                                onClick={() => setApproveAllOpen(true)}
                            >
                                Aprobar Todos
                            </Button>
                        )}
                        {!isClosed && can.add_adjustment && (
                            <Button onClick={() => openAddAdjustment()}>
                                + Agregar Ajuste
                            </Button>
                        )}
                        <a
                            href={periodExport.url(period)}
                            download
                            className="inline-flex items-center gap-2 rounded-md border border-border bg-background px-3 py-2 text-sm font-medium text-foreground hover:bg-muted"
                            title={!isClosed ? 'Los datos son provisionales — el período aún está abierto' : undefined}
                        >
                            <Download className="h-4 w-4" />
                            Exportar CSV
                        </a>
                    </div>
                </div>

                {/* Closed banner */}
                {isClosed && (
                    <div className="flex items-center gap-2 rounded-md border border-border bg-muted/50 px-4 py-3 text-sm text-muted-foreground">
                        <Info className="h-4 w-4 shrink-0" />
                        Este período está cerrado. Solo lectura.
                    </div>
                )}

                {/* Pending commissions banner — commissions auto-assigned but records not yet generated */}
                {!isClosed && pending_commissions_count > 0 && (
                    <div className="flex items-center gap-2 rounded-md border border-amber-400/40 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:bg-amber-950/20 dark:text-amber-300">
                        <AlertTriangle className="h-4 w-4 shrink-0 text-amber-500" />
                        <span>
                            Hay <strong>{pending_commissions_count}</strong> comisión
                            {pending_commissions_count !== 1 ? 'es' : ''} cobrada
                            {pending_commissions_count !== 1 ? 's' : ''} asignada
                            {pending_commissions_count !== 1 ? 's' : ''} a este período sin record de nómina generado.
                            Haz clic en <strong>Generar Records</strong> para incluirlas en la nómina.
                        </span>
                    </div>
                )}

                {/* Summary cards */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-xs text-muted-foreground">Total Bruto</p>
                            <p className="mt-1 text-lg font-bold text-foreground">
                                {formatCurrency(period_summary.total_gross)}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-xs text-muted-foreground">Empleados</p>
                            <p className="mt-1 text-lg font-bold text-foreground">
                                {records.length}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-xs text-muted-foreground">Borrador</p>
                            <p className="mt-1 text-lg font-bold text-foreground">
                                {period_summary.draft_count}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-xs text-muted-foreground">Aprobados</p>
                            <p className="mt-1 text-lg font-bold text-[var(--color-amber-brand)]">
                                {period_summary.approved_count}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-xs text-muted-foreground">Pagados</p>
                            <p className="mt-1 text-lg font-bold text-[var(--color-green-brand)]">
                                {period_summary.paid_count}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Records table or empty state */}
                {!period.has_records ? (
                    <EmptyState
                        icon={Users}
                        title="Aún no se han generado los records de este período"
                        description="Genera los records para ver el desglose de nómina de cada empleado."
                        action={
                            can.generate
                                ? {
                                      label: 'Generar Records',
                                      onClick: () => router.post(periodGenerate.url(period), {}),
                                  }
                                : undefined
                        }
                    />
                ) : (
                    <div className="rounded-lg border border-border bg-card overflow-hidden">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-8" />
                                    <TableHead>Empleado</TableHead>
                                    <TableHead className="text-right">Base</TableHead>
                                    <TableHead className="text-right">Comisiones</TableHead>
                                    <TableHead className="text-right">Tips</TableHead>
                                    <TableHead className="text-right">Ajustes</TableHead>
                                    <TableHead className="text-right">Bruto</TableHead>
                                    <TableHead>Estado</TableHead>
                                    <TableHead className="text-right">Acciones</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {records.map((record) => {
                                    const isExpanded = expandedRows.has(record.id);
                                    const isProcessing = processingId === record.id;
                                    const isNegative = record.gross_total.startsWith('-');
                                    const employeeName =
                                        record.employee?.user?.name ?? `Empleado #${record.employee_id}`;

                                    return (
                                        <Collapsible
                                            key={record.id}
                                            open={isExpanded}
                                            onOpenChange={() => toggleRow(record.id)}
                                            asChild
                                        >
                                            <>
                                                <TableRow
                                                    className={isNegative ? 'bg-red-50 dark:bg-red-950/10' : undefined}
                                                >
                                                    <TableCell className="py-2">
                                                        <CollapsibleTrigger asChild>
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="h-6 w-6"
                                                            >
                                                                {isExpanded ? (
                                                                    <ChevronDown className="h-4 w-4" />
                                                                ) : (
                                                                    <ChevronRight className="h-4 w-4" />
                                                                )}
                                                            </Button>
                                                        </CollapsibleTrigger>
                                                    </TableCell>
                                                    <TableCell className="py-2">
                                                        <Link
                                                            href={periodEmployee.url({
                                                                period: period.id,
                                                                employee: record.employee_id,
                                                            })}
                                                            className="font-medium text-foreground hover:underline"
                                                        >
                                                            {employeeName}
                                                        </Link>
                                                    </TableCell>
                                                    <TableCell className="py-2 text-right text-sm text-muted-foreground">
                                                        {formatCurrency(record.base_salary_snapshot)}
                                                    </TableCell>
                                                    <TableCell className="py-2 text-right text-sm text-muted-foreground">
                                                        {formatCurrency(record.commissions_total)}
                                                    </TableCell>
                                                    <TableCell className="py-2 text-right text-sm text-muted-foreground">
                                                        {formatCurrency(record.tips_total)}
                                                    </TableCell>
                                                    <TableCell className="py-2 text-right text-sm text-muted-foreground">
                                                        {formatCurrency(record.adjustments_total)}
                                                    </TableCell>
                                                    <TableCell className="py-2 text-right">
                                                        <NegativeGrossBadge amount={record.gross_total} />
                                                    </TableCell>
                                                    <TableCell className="py-2">
                                                        <PayrollRecordStatusBadge status={record.status} />
                                                    </TableCell>
                                                    <TableCell className="py-2">
                                                        <div className="flex items-center justify-end gap-1">
                                                            {!isClosed && record.status === 'approved' && (
                                                                <Button
                                                                    size="sm"
                                                                    variant="outline"
                                                                    disabled={isProcessing}
                                                                    onClick={() => setMarkPaidRecord(record)}
                                                                >
                                                                    Pagar
                                                                </Button>
                                                            )}
                                                            {!isClosed &&
                                                                (record.status === 'draft' || record.status === 'approved') && (
                                                                    <Button
                                                                        size="sm"
                                                                        variant="outline"
                                                                        disabled={isProcessing}
                                                                        onClick={() => setVoidRecord(record)}
                                                                    >
                                                                        Anular
                                                                    </Button>
                                                                )}
                                                            {!isClosed && record.status === 'paid' && (
                                                                next_open_period ? (
                                                                    <Button
                                                                        size="sm"
                                                                        variant="outline"
                                                                        disabled={isProcessing}
                                                                        onClick={() => setVoidRecord(record)}
                                                                    >
                                                                        Anular
                                                                    </Button>
                                                                ) : (
                                                                    <Tooltip>
                                                                        <TooltipTrigger asChild>
                                                                            <span className="inline-flex">
                                                                                <Button
                                                                                    size="sm"
                                                                                    variant="outline"
                                                                                    disabled
                                                                                >
                                                                                    Anular
                                                                                </Button>
                                                                            </span>
                                                                        </TooltipTrigger>
                                                                        <TooltipContent className="max-w-xs text-xs">
                                                                            No hay un período abierto posterior a{' '}
                                                                            {period.ends_on}. Crea el siguiente período
                                                                            para poder anular este pago.
                                                                        </TooltipContent>
                                                                    </Tooltip>
                                                                )
                                                            )}
                                                            {!isClosed && can.add_adjustment && record.status === 'draft' && (
                                                                <Button
                                                                    size="sm"
                                                                    variant="ghost"
                                                                    className="text-xs"
                                                                    onClick={() => openAddAdjustment(record.employee)}
                                                                >
                                                                    + Ajuste
                                                                </Button>
                                                            )}
                                                        </div>
                                                    </TableCell>
                                                </TableRow>

                                                <TableRow className="hover:bg-transparent">
                                                    <TableCell colSpan={9} className="p-0">
                                                        <CollapsibleContent>
                                                            <div className="space-y-4 border-t border-border/50 bg-muted/30 px-6 py-4">
                                                                {/* Commissions */}
                                                                <div>
                                                                    <h4 className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                                                        Comisiones ({record.commissions?.length ?? 0})
                                                                    </h4>
                                                                    {record.commissions && record.commissions.length > 0 ? (
                                                                        <table className="w-full text-sm">
                                                                            <thead>
                                                                                <tr className="text-left text-xs text-muted-foreground">
                                                                                    <th className="pb-1 pr-4 font-normal">Servicio</th>
                                                                                    <th className="pb-1 pr-4 text-right font-normal">Precio</th>
                                                                                    <th className="pb-1 pr-4 font-normal">Regla</th>
                                                                                    <th className="pb-1 pr-4 text-right font-normal">Comisión</th>
                                                                                    <th className="pb-1 font-normal">Fecha cita</th>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody>
                                                                                {record.commissions.map((c) => (
                                                                                    <tr key={c.id} className="border-t border-border/30">
                                                                                        <td className="py-1 pr-4">
                                                                                            {c.service?.name ?? `Servicio #${c.service_id}`}
                                                                                            {c.is_retroactive && (
                                                                                                <Tooltip>
                                                                                                    <TooltipTrigger asChild>
                                                                                                        <Badge
                                                                                                            variant="outline"
                                                                                                            className="ml-2 border-amber-400 text-xs text-amber-600 dark:text-amber-400"
                                                                                                        >
                                                                                                            Retroactiva
                                                                                                        </Badge>
                                                                                                    </TooltipTrigger>
                                                                                                    <TooltipContent className="max-w-xs text-xs">
                                                                                                        Esta comisión corresponde a una cita anterior al período pero fue generada después de su cierre.
                                                                                                    </TooltipContent>
                                                                                                </Tooltip>
                                                                                            )}
                                                                                        </td>
                                                                                        <td className="py-1 pr-4 text-right text-muted-foreground">
                                                                                            {formatCurrency(c.service_price_snapshot)}
                                                                                        </td>
                                                                                        <td className="py-1 pr-4 text-muted-foreground">
                                                                                            {ruleLabel(c.rule_type_snapshot, c.rule_value_snapshot)}
                                                                                        </td>
                                                                                        <td className="py-1 pr-4 text-right font-medium">
                                                                                            {formatCurrency(c.commission_amount)}
                                                                                        </td>
                                                                                        <td className="py-1 text-muted-foreground">
                                                                                            {c.appointment?.scheduled_at
                                                                                                ? new Date(c.appointment.scheduled_at).toLocaleDateString('es-MX')
                                                                                                : '—'}
                                                                                        </td>
                                                                                    </tr>
                                                                                ))}
                                                                            </tbody>
                                                                        </table>
                                                                    ) : (
                                                                        <p className="text-xs text-muted-foreground">
                                                                            Sin comisiones en este período.
                                                                        </p>
                                                                    )}
                                                                </div>

                                                                {/* Tips */}
                                                                <div>
                                                                    <h4 className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                                                        Tips ({record.tips?.length ?? 0})
                                                                    </h4>
                                                                    {record.tips && record.tips.length > 0 ? (
                                                                        <div className="flex flex-wrap gap-2">
                                                                            {record.tips.map((t) => (
                                                                                <span
                                                                                    key={t.id}
                                                                                    className="rounded-md bg-muted px-2 py-1 text-xs"
                                                                                >
                                                                                    {formatCurrency(t.amount)} — {t.received_at}
                                                                                </span>
                                                                            ))}
                                                                        </div>
                                                                    ) : (
                                                                        <p className="text-xs text-muted-foreground">
                                                                            Sin propinas en este período.
                                                                        </p>
                                                                    )}
                                                                </div>

                                                                {/* Adjustments */}
                                                                <div>
                                                                    <h4 className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                                                        Ajustes ({record.adjustments?.length ?? 0})
                                                                    </h4>
                                                                    {record.adjustments && record.adjustments.length > 0 ? (
                                                                        <table className="w-full text-sm">
                                                                            <thead>
                                                                                <tr className="text-left text-xs text-muted-foreground">
                                                                                    <th className="pb-1 pr-4 font-normal">Tipo</th>
                                                                                    <th className="pb-1 pr-4 text-right font-normal">Monto</th>
                                                                                    <th className="pb-1 pr-4 font-normal">Razón</th>
                                                                                    <th className="pb-1 font-normal">Fecha</th>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody>
                                                                                {record.adjustments.map((a) => {
                                                                                    const isCompensation = a.reason?.startsWith('Void compensation:');
                                                                                    return (
                                                                                        <tr key={a.id} className="border-t border-border/30">
                                                                                            <td className="py-1 pr-4">
                                                                                                <Badge
                                                                                                    variant={
                                                                                                        a.type === 'credit'
                                                                                                            ? 'default'
                                                                                                            : 'destructive'
                                                                                                    }
                                                                                                    className="text-xs"
                                                                                                >
                                                                                                    {a.type === 'credit' ? 'Crédito' : 'Débito'}
                                                                                                </Badge>
                                                                                            </td>
                                                                                            <td
                                                                                                className={`py-1 pr-4 text-right font-medium ${
                                                                                                    a.type === 'debit'
                                                                                                        ? 'text-destructive'
                                                                                                        : 'text-[var(--color-green-brand)]'
                                                                                                }`}
                                                                                            >
                                                                                                {a.type === 'debit' ? '-' : '+'}
                                                                                                {formatCurrency(a.amount)}
                                                                                            </td>
                                                                                            <td className="py-1 pr-4 text-muted-foreground">
                                                                                                {isCompensation ? (
                                                                                                    <span className="flex items-center gap-1">
                                                                                                        <AlertTriangle className="h-3.5 w-3.5 text-amber-500" />
                                                                                                        Compensación por anulación
                                                                                                    </span>
                                                                                                ) : (
                                                                                                    a.reason
                                                                                                )}
                                                                                            </td>
                                                                                            <td className="py-1 text-muted-foreground">
                                                                                                {new Date(a.created_at).toLocaleDateString('es-MX')}
                                                                                            </td>
                                                                                        </tr>
                                                                                    );
                                                                                })}
                                                                            </tbody>
                                                                        </table>
                                                                    ) : (
                                                                        <p className="text-xs text-muted-foreground">
                                                                            Sin ajustes en este período.
                                                                        </p>
                                                                    )}
                                                                </div>
                                                            </div>
                                                        </CollapsibleContent>
                                                    </TableCell>
                                                </TableRow>
                                            </>
                                        </Collapsible>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </div>

            {/* Approve All Modal */}
            <ConfirmationModal
                open={approveAllOpen}
                onOpenChange={setApproveAllOpen}
                title="Aprobar Todos los Records"
                description="¿Aprobar todos los records en estado Borrador? Esta acción bloqueará las comisiones del período."
                confirmLabel="Aprobar Todos"
                cancelLabel="Cancelar"
                onConfirm={handleApproveAll}
            />

            {/* Mark Paid Modal */}
            {markPaidRecord && (
                <MarkPaidModal
                    open
                    onOpenChange={(open) => {
                        if (!open) setMarkPaidRecord(null);
                    }}
                    record={markPaidRecord}
                    onConfirm={handleMarkPaid}
                    processing={processingId === markPaidRecord.id}
                />
            )}

            {/* Void Confirmation Modal */}
            {voidRecord && (
                <VoidConfirmationModal
                    open
                    onOpenChange={(open) => {
                        if (!open) setVoidRecord(null);
                    }}
                    record={voidRecord}
                    nextPeriod={next_open_period}
                    onConfirm={handleVoid}
                    processing={processingId === voidRecord.id}
                />
            )}

            {/* Add Adjustment Modal */}
            <AddAdjustmentModal
                open={addAdjustmentOpen}
                onOpenChange={(open) => {
                    setAddAdjustmentOpen(open);
                    if (!open) setAddAdjustmentPreselect(undefined);
                }}
                period={period}
                employees={employees}
                preselectedEmployee={addAdjustmentPreselect}
                onConfirm={handleAddAdjustment}
            />
        </AppLayout>
    );
}
