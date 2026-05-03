import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { PayrollRecordStatusBadge } from '@/components/payroll-record-status-badge';
import { NegativeGrossBadge } from '@/components/negative-gross-badge';
import { show as periodShow } from '@/actions/App/Http/Controllers/Payroll/PayrollPeriodController';
import type { Employee, PayrollPeriod, PayrollRecord, CommissionRecord, Tip, PayrollAdjustment, User } from '@/types/models';
import type { BreadcrumbItem } from '@/types';
import { AlertTriangle, ArrowLeft, CheckCircle, Circle } from 'lucide-react';

type FullRecord = PayrollRecord & {
    employee: Employee & { user: User };
    commissions: CommissionRecord[];
    tips: Tip[];
    adjustments: PayrollAdjustment[];
};

interface Transition {
    from: string | null;
    to: string;
    at: string;
    by_name: string;
}

interface Props {
    period: PayrollPeriod;
    record: FullRecord;
    transitions: Transition[];
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

function transitionLabel(to: string): string {
    const labels: Record<string, string> = {
        draft: 'Generado',
        approved: 'Aprobado',
        paid: 'Pagado',
        voided: 'Anulado',
    };
    return labels[to] ?? to;
}

export default function Employee({ period, record, transitions }: Props) {
    const label = periodLabel(period.starts_on);
    const employeeName = record.employee?.user?.name ?? `Empleado #${record.employee_id}`;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Períodos de Nómina', href: '/payroll/periods' },
        { title: label, href: periodShow.url(period) },
        { title: employeeName, href: '#' },
    ];

    return (
        <AppLayout title={`${employeeName} — ${label}`} breadcrumbs={breadcrumbs}>
            <Head title={`${employeeName} — ${label}`} />

            <div className="mx-auto max-w-4xl space-y-6">
                {/* Header */}
                <div className="space-y-1">
                    <Link
                        href={periodShow.url(period)}
                        className="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Volver al período
                    </Link>
                    <div className="flex flex-wrap items-center gap-3">
                        <h1 className="text-2xl font-bold text-foreground">{employeeName}</h1>
                        <span className="text-muted-foreground">—</span>
                        <span className="capitalize text-muted-foreground">{label}</span>
                        <PayrollRecordStatusBadge status={record.status} />
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {period.starts_on} — {period.ends_on}
                    </p>
                </div>

                {/* Summary cards */}
                <div className="grid gap-4 sm:grid-cols-4">
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-xs text-muted-foreground">Base</p>
                            <p className="mt-1 text-lg font-bold text-foreground">
                                {formatCurrency(record.base_salary_snapshot)}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-xs text-muted-foreground">Comisiones</p>
                            <p className="mt-1 text-lg font-bold text-foreground">
                                {formatCurrency(record.commissions_total)}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-xs text-muted-foreground">Tips</p>
                            <p className="mt-1 text-lg font-bold text-foreground">
                                {formatCurrency(record.tips_total)}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-xs text-muted-foreground">Bruto</p>
                            <div className="mt-1">
                                <NegativeGrossBadge amount={record.gross_total} />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Commissions */}
                <section>
                    <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                        Comisiones ({record.commissions?.length ?? 0})
                    </h2>
                    {record.commissions && record.commissions.length > 0 ? (
                        <div className="rounded-lg border border-border bg-card overflow-hidden">
                            <table className="w-full text-sm">
                                <thead className="border-b border-border bg-muted/30">
                                    <tr className="text-left text-xs text-muted-foreground">
                                        <th className="px-4 py-2 font-medium">Servicio</th>
                                        <th className="px-4 py-2 text-right font-medium">Precio</th>
                                        <th className="px-4 py-2 font-medium">Regla</th>
                                        <th className="px-4 py-2 text-right font-medium">Snapshot</th>
                                        <th className="px-4 py-2 text-right font-medium">Comisión</th>
                                        <th className="px-4 py-2 font-medium">Fecha cita</th>
                                        <th className="px-4 py-2 font-medium">Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {record.commissions.map((c) => (
                                        <tr key={c.id} className="border-t border-border/50">
                                            <td className="px-4 py-2">
                                                <span>{c.service?.name ?? `Servicio #${c.service_id}`}</span>
                                                {c.is_retroactive && (
                                                    <Badge
                                                        variant="outline"
                                                        className="ml-2 border-amber-400 text-xs text-amber-600 dark:text-amber-400"
                                                    >
                                                        Retroactiva
                                                    </Badge>
                                                )}
                                            </td>
                                            <td className="px-4 py-2 text-right text-muted-foreground">
                                                {formatCurrency(c.service_price_snapshot)}
                                            </td>
                                            <td className="px-4 py-2 text-muted-foreground">
                                                {ruleLabel(c.rule_type_snapshot, c.rule_value_snapshot)}
                                            </td>
                                            <td className="px-4 py-2 text-right text-muted-foreground">
                                                {formatCurrency(c.service_price_snapshot)}
                                            </td>
                                            <td className="px-4 py-2 text-right font-medium text-foreground">
                                                {formatCurrency(c.commission_amount)}
                                            </td>
                                            <td className="px-4 py-2 text-muted-foreground">
                                                {c.appointment?.scheduled_at
                                                    ? new Date(c.appointment.scheduled_at).toLocaleDateString('es-MX')
                                                    : '—'}
                                            </td>
                                            <td className="px-4 py-2">
                                                <span className="text-xs text-muted-foreground">{c.status}</span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <p className="text-sm text-muted-foreground">Sin comisiones en este período.</p>
                    )}
                </section>

                <Separator />

                {/* Tips */}
                <section>
                    <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                        Tips ({record.tips?.length ?? 0})
                    </h2>
                    {record.tips && record.tips.length > 0 ? (
                        <div className="rounded-lg border border-border bg-card overflow-hidden">
                            <table className="w-full text-sm">
                                <thead className="border-b border-border bg-muted/30">
                                    <tr className="text-left text-xs text-muted-foreground">
                                        <th className="px-4 py-2 font-medium">Monto</th>
                                        <th className="px-4 py-2 font-medium">Fecha recibida</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {record.tips.map((t) => (
                                        <tr key={t.id} className="border-t border-border/50">
                                            <td className="px-4 py-2 font-medium text-foreground">
                                                {formatCurrency(t.amount)}
                                            </td>
                                            <td className="px-4 py-2 text-muted-foreground">
                                                {t.received_at}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <p className="text-sm text-muted-foreground">Sin propinas en este período.</p>
                    )}
                </section>

                <Separator />

                {/* Adjustments */}
                <section>
                    <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                        Ajustes ({record.adjustments?.length ?? 0})
                    </h2>
                    {record.adjustments && record.adjustments.length > 0 ? (
                        <div className="rounded-lg border border-border bg-card overflow-hidden">
                            <table className="w-full text-sm">
                                <thead className="border-b border-border bg-muted/30">
                                    <tr className="text-left text-xs text-muted-foreground">
                                        <th className="px-4 py-2 font-medium">Tipo</th>
                                        <th className="px-4 py-2 text-right font-medium">Monto</th>
                                        <th className="px-4 py-2 font-medium">Razón</th>
                                        <th className="px-4 py-2 font-medium">Creado por</th>
                                        <th className="px-4 py-2 font-medium">Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {record.adjustments.map((a) => {
                                        const isCompensation = a.reason?.startsWith('Void compensation:');
                                        return (
                                            <tr key={a.id} className="border-t border-border/50">
                                                <td className="px-4 py-2">
                                                    <Badge
                                                        variant={a.type === 'credit' ? 'default' : 'destructive'}
                                                        className="text-xs"
                                                    >
                                                        {a.type === 'credit' ? 'Crédito' : 'Débito'}
                                                    </Badge>
                                                </td>
                                                <td
                                                    className={`px-4 py-2 text-right font-medium ${
                                                        a.type === 'debit'
                                                            ? 'text-destructive'
                                                            : 'text-[var(--color-green-brand)]'
                                                    }`}
                                                >
                                                    {a.type === 'debit' ? '-' : '+'}{formatCurrency(a.amount)}
                                                </td>
                                                <td className="px-4 py-2 text-muted-foreground">
                                                    {isCompensation ? (
                                                        <span className="flex items-center gap-1 text-amber-600 dark:text-amber-400">
                                                            <AlertTriangle className="h-3.5 w-3.5" />
                                                            Compensación por anulación
                                                        </span>
                                                    ) : (
                                                        a.reason
                                                    )}
                                                </td>
                                                <td className="px-4 py-2 text-muted-foreground">
                                                    {(a.creator as User | undefined)?.name ?? '—'}
                                                </td>
                                                <td className="px-4 py-2 text-muted-foreground">
                                                    {new Date(a.created_at).toLocaleDateString('es-MX')}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <p className="text-sm text-muted-foreground">Sin ajustes en este período.</p>
                    )}
                </section>

                <Separator />

                {/* Transition timeline */}
                <section>
                    <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                        Historial de Transiciones
                    </h2>
                    <ol className="space-y-3">
                        {transitions.map((t, i) => (
                            <li key={i} className="flex items-start gap-3">
                                <div className="mt-0.5 shrink-0">
                                    {t.to === 'voided' ? (
                                        <AlertTriangle className="h-5 w-5 text-destructive" />
                                    ) : t.to === 'paid' ? (
                                        <CheckCircle className="h-5 w-5 text-[var(--color-green-brand)]" />
                                    ) : (
                                        <Circle className="h-5 w-5 text-muted-foreground" />
                                    )}
                                </div>
                                <div>
                                    <p className="text-sm font-medium text-foreground">
                                        {transitionLabel(t.to)}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {new Date(t.at).toLocaleString('es-MX')}
                                        {t.by_name && t.by_name !== '—' && ` · por ${t.by_name}`}
                                    </p>
                                </div>
                            </li>
                        ))}
                    </ol>
                </section>
            </div>
        </AppLayout>
    );
}
