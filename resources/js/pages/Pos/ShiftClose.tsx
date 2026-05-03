import { useMemo } from 'react';
import { router, useForm, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { BreadcrumbItem } from '@/types';
import { AlertCircle, Receipt, Banknote, TrendingUp } from 'lucide-react';

interface ShiftSummary {
    tickets_count: number;
    total_sales: string;
    total_tips: string;
    by_method: { cash: string; card: string; transfer: string };
}

interface ShiftCloseProps {
    cashier: { id: number; name: string };
    today: string;
    shift_summary: ShiftSummary;
    existing_shift: { shift_date: string; cashier_id: number; total_sales: string } | null;
    employees: Array<{ id: number; user: { name: string } }>;
}

interface ShiftFormData {
    cashier_id: string;
    shift_date: string;
    opened_at: string;
    closed_at: string;
    opening_cash: string;
    closing_cash_counted: string;
    difference_reason: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'POS', href: '/pos' },
    { title: 'Cierre de Turno', href: '/pos/shift-close' },
];

function formatCurrency(amount: string | number): string {
    const num = typeof amount === 'string' ? parseFloat(amount) : amount;
    return 'RD$' + num.toLocaleString('es-DO', { minimumFractionDigits: 2 });
}

function nowTime(): string {
    const now = new Date();
    const hh = String(now.getHours()).padStart(2, '0');
    const mm = String(now.getMinutes()).padStart(2, '0');
    return `${hh}:${mm}`;
}

export default function ShiftClose({
    cashier,
    today,
    shift_summary,
    existing_shift,
    employees,
}: ShiftCloseProps) {
    const { data, setData, post, processing, errors } = useForm<ShiftFormData>({
        cashier_id: String(cashier.id),
        shift_date: today,
        opened_at: '',
        closed_at: nowTime(),
        opening_cash: '0.00',
        closing_cash_counted: '',
        difference_reason: '',
    });

    const cashExpected = useMemo(() => {
        const opening = parseFloat(data.opening_cash) || 0;
        const cashSales = parseFloat(shift_summary.by_method.cash) || 0;
        return opening + cashSales;
    }, [data.opening_cash, shift_summary.by_method.cash]);

    const difference = useMemo(() => {
        const counted = parseFloat(data.closing_cash_counted) || 0;
        return counted - cashExpected;
    }, [data.closing_cash_counted, cashExpected]);

    const absDifference = Math.abs(difference);

    const isFormValid = useMemo(() => {
        if (!data.closing_cash_counted) { return false; }
        if (absDifference > 0 && data.difference_reason.trim().length < 5) { return false; }
        return true;
    }, [data.closing_cash_counted, absDifference, data.difference_reason]);

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        post('/pos/shift-close', {
            onSuccess: () => router.visit('/pos'),
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-3xl space-y-6 p-6">
                {/* Header */}
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-foreground">
                        Cierre de Turno
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {cashier.name} · {today}
                    </p>
                </div>

                {/* Existing shift banner */}
                {existing_shift !== null && (
                    <Alert>
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>
                            Ya existe un cierre registrado para hoy ({today}) por este cajero.
                            Si necesitas corrección, contacta al administrador.
                        </AlertDescription>
                    </Alert>
                )}

                {/* Summary cards */}
                <div className="grid gap-4 sm:grid-cols-3">
                    <Card>
                        <CardHeader className="pb-2">
                            <div className="flex items-center gap-2 text-muted-foreground">
                                <Receipt className="h-4 w-4" />
                                <CardTitle className="text-sm font-medium">Tickets cobrados</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-bold text-foreground">
                                {shift_summary.tickets_count}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <div className="flex items-center gap-2 text-muted-foreground">
                                <TrendingUp className="h-4 w-4" />
                                <CardTitle className="text-sm font-medium">Total ventas</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-bold text-foreground">
                                {formatCurrency(shift_summary.total_sales)}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <div className="flex items-center gap-2 text-muted-foreground">
                                <Banknote className="h-4 w-4" />
                                <CardTitle className="text-sm font-medium">Total propinas</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-bold text-foreground">
                                {formatCurrency(shift_summary.total_tips)}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Method breakdown */}
                <div className="rounded-lg border border-border bg-card p-4">
                    <h2 className="mb-3 font-semibold text-foreground">Desglose por método</h2>
                    <div className="grid gap-3 sm:grid-cols-3">
                        <div className="flex items-center justify-between rounded-md bg-muted/50 p-3">
                            <span className="text-sm text-muted-foreground">Efectivo</span>
                            <span className="font-medium text-foreground">
                                {formatCurrency(shift_summary.by_method.cash)}
                            </span>
                        </div>
                        <div className="flex items-center justify-between rounded-md bg-muted/50 p-3">
                            <span className="text-sm text-muted-foreground">Tarjeta</span>
                            <span className="font-medium text-foreground">
                                {formatCurrency(shift_summary.by_method.card)}
                            </span>
                        </div>
                        <div className="flex items-center justify-between rounded-md bg-muted/50 p-3">
                            <span className="text-sm text-muted-foreground">Transferencia</span>
                            <span className="font-medium text-foreground">
                                {formatCurrency(shift_summary.by_method.transfer)}
                            </span>
                        </div>
                    </div>
                </div>

                {/* Read-only view when shift already exists */}
                {existing_shift !== null ? (
                    <div className="rounded-lg border border-border bg-card p-4 space-y-2">
                        <h2 className="font-semibold text-foreground">Cierre registrado</h2>
                        <p className="text-sm text-muted-foreground">
                            Total ventas registrado: {formatCurrency(existing_shift.total_sales)}
                        </p>
                        <div className="pt-2">
                            <Link href="/pos">
                                <Button variant="outline">Volver al POS</Button>
                            </Link>
                        </div>
                    </div>
                ) : (
                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* Form fields */}
                        <div className="rounded-lg border border-border bg-card p-4 space-y-4">
                            <h2 className="font-semibold text-foreground">Información del turno</h2>

                            {/* Cashier select (if multiple employees) */}
                            {employees.length > 1 && (
                                <div className="space-y-1">
                                    <Label htmlFor="cashier_id">Cajero</Label>
                                    <Select
                                        value={data.cashier_id}
                                        onValueChange={(v) => setData('cashier_id', v)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Seleccionar cajero" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {employees.map((emp) => (
                                                <SelectItem key={emp.id} value={String(emp.id)}>
                                                    {emp.user.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.cashier_id && (
                                        <p className="text-sm text-destructive">{errors.cashier_id}</p>
                                    )}
                                </div>
                            )}

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-1">
                                    <Label htmlFor="opened_at">Hora de apertura (opcional)</Label>
                                    <Input
                                        id="opened_at"
                                        type="time"
                                        value={data.opened_at}
                                        onChange={(e) => setData('opened_at', e.target.value)}
                                    />
                                </div>
                                <div className="space-y-1">
                                    <Label htmlFor="closed_at">Hora de cierre</Label>
                                    <Input
                                        id="closed_at"
                                        type="time"
                                        value={data.closed_at}
                                        onChange={(e) => setData('closed_at', e.target.value)}
                                    />
                                </div>
                            </div>
                        </div>

                        {/* Cash reconciliation */}
                        <div className="rounded-lg border border-border bg-card p-4 space-y-4">
                            <h2 className="font-semibold text-foreground">Cuadre de caja</h2>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-1">
                                    <Label htmlFor="opening_cash">Efectivo inicial (RD$)</Label>
                                    <Input
                                        id="opening_cash"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        value={data.opening_cash}
                                        onChange={(e) => setData('opening_cash', e.target.value)}
                                    />
                                    {errors.opening_cash && (
                                        <p className="text-sm text-destructive">{errors.opening_cash}</p>
                                    )}
                                </div>
                                <div className="space-y-1">
                                    <Label htmlFor="closing_cash_counted">Contado físico (RD$) *</Label>
                                    <Input
                                        id="closing_cash_counted"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        placeholder="0.00"
                                        value={data.closing_cash_counted}
                                        onChange={(e) => setData('closing_cash_counted', e.target.value)}
                                    />
                                    {errors.closing_cash_counted && (
                                        <p className="text-sm text-destructive">{errors.closing_cash_counted}</p>
                                    )}
                                </div>
                            </div>

                            {/* Reconciliation summary */}
                            <div className="rounded-md bg-muted/50 p-4 space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Efectivo inicial</span>
                                    <span className="text-foreground">{formatCurrency(data.opening_cash || '0')}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">+ Cobros efectivo</span>
                                    <span className="text-foreground">{formatCurrency(shift_summary.by_method.cash)}</span>
                                </div>
                                <div className="flex justify-between border-t border-border pt-2">
                                    <span className="text-muted-foreground">= Esperado en caja</span>
                                    <span className="font-medium text-foreground">{formatCurrency(cashExpected)}</span>
                                </div>
                                {data.closing_cash_counted && (
                                    <>
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">Contado físico</span>
                                            <span className="text-foreground">{formatCurrency(data.closing_cash_counted)}</span>
                                        </div>
                                        <div className="flex justify-between border-t border-border pt-2 font-medium">
                                            <span className="text-muted-foreground">Diferencia</span>
                                            <span
                                                className={
                                                    absDifference === 0
                                                        ? 'text-[var(--color-green-brand)]'
                                                        : 'text-[var(--color-amber-brand)]'
                                                }
                                            >
                                                {difference >= 0 ? '+' : ''}{formatCurrency(difference)}
                                            </span>
                                        </div>
                                    </>
                                )}
                            </div>

                            {/* Difference reason */}
                            {absDifference > 0 && (
                                <div className="space-y-1">
                                    <Label htmlFor="difference_reason">
                                        Razón de la diferencia *
                                    </Label>
                                    <Textarea
                                        id="difference_reason"
                                        value={data.difference_reason}
                                        onChange={(e) => setData('difference_reason', e.target.value)}
                                        placeholder="Explica la diferencia encontrada en caja..."
                                        rows={3}
                                    />
                                    {errors.difference_reason && (
                                        <p className="text-sm text-destructive">{errors.difference_reason}</p>
                                    )}
                                </div>
                            )}
                        </div>

                        {/* Actions */}
                        <div className="flex items-center justify-end gap-3">
                            <Link href="/pos">
                                <Button type="button" variant="outline">
                                    Cancelar
                                </Button>
                            </Link>
                            <Button
                                type="submit"
                                disabled={!isFormValid || processing}
                            >
                                {processing ? 'Guardando...' : 'Confirmar Cierre'}
                            </Button>
                        </div>
                    </form>
                )}
            </div>
        </AppLayout>
    );
}
