import { useState } from 'react';
import { router, useForm, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { PosTicketStatusBadge } from '@/components/pos/pos-ticket-status-badge';
import { EcfStatusBadge } from '@/components/pos/ecf-status-badge';
import type { BreadcrumbItem } from '@/types';
import {
    AlertCircle,
    ArrowLeft,
    CreditCard,
    Banknote,
    ArrowLeftRight,
    Calendar,
    User,
} from 'lucide-react';
import { format } from 'date-fns';

interface PosTicketItem {
    id: number;
    item_type: 'service' | 'product';
    name: string;
    unit_price: string;
    qty: number;
    line_total: string;
    employee: { user: { name: string } } | null;
}

interface PosPayment {
    id: number;
    method: 'cash' | 'card' | 'transfer';
    amount: string;
    reference: string | null;
    cash_tendered: string | null;
    cash_change: string | null;
}

interface TicketDetail {
    id: number;
    ticket_number: string;
    status: 'open' | 'paid' | 'voided';
    subtotal: string;
    discount_amount: string;
    itbis_amount: string;
    itbis_pct: string;
    tip_amount: string;
    total: string;
    ecf_status: 'na' | 'pending' | 'emitted' | 'error' | 'offline_pending';
    ecf_ncf: string | null;
    ecf_type: 'consumidor_final' | 'credito_fiscal' | null;
    ecf_error_message: string | null;
    ecf_emitted_at: string | null;
    void_reason: string | null;
    voided_at: string | null;
    client_name: string | null;
    client_rnc: string | null;
    notes: string | null;
    created_at: string;
    items: PosTicketItem[];
    payments: PosPayment[];
    appointment: { id: number; scheduled_at: string } | null;
    employee: { id: number; user: { name: string } } | null;
    cashier: { id: number; name: string } | null;
}

interface TicketShowProps {
    ticket: TicketDetail;
    can: { void: boolean };
}

const METHOD_LABELS: Record<string, string> = {
    cash: 'Efectivo',
    card: 'Tarjeta',
    transfer: 'Transferencia',
};

const METHOD_ICONS: Record<string, React.ReactNode> = {
    cash: <Banknote className="h-4 w-4 text-[var(--color-green-brand)]" />,
    card: <CreditCard className="h-4 w-4 text-blue-500" />,
    transfer: <ArrowLeftRight className="h-4 w-4 text-purple-500" />,
};

function formatCurrency(amount: string | number): string {
    const num = typeof amount === 'string' ? parseFloat(amount) : amount;
    return 'RD$' + num.toLocaleString('es-DO', { minimumFractionDigits: 2 });
}

export default function TicketShow({ ticket, can }: TicketShowProps) {
    const [showVoidModal, setShowVoidModal] = useState(false);
    const [voidReason, setVoidReason] = useState('');

    const { post, processing } = useForm<{ reason: string }>({ reason: '' });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'POS', href: '/pos' },
        { title: 'Tickets', href: '/pos/tickets' },
        { title: ticket.ticket_number, href: `/pos/tickets/${ticket.id}` },
    ];

    function handleVoid() {
        post(`/pos/tickets/${ticket.id}/void`, {
            data: { reason: voidReason },
            onSuccess: () => {
                setShowVoidModal(false);
                setVoidReason('');
            },
        });
    }

    const paymentMethodLabel = ticket.payments
        .map((p) => METHOD_LABELS[p.method] ?? p.method)
        .join(' + ');

    const discount = parseFloat(ticket.discount_amount);
    const tip = parseFloat(ticket.tip_amount);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-4xl space-y-6 p-6">
                {/* Voided banner */}
                {ticket.status === 'voided' && (
                    <Alert variant="destructive">
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>
                            Este ticket fue anulado el{' '}
                            {ticket.voided_at
                                ? format(new Date(ticket.voided_at), 'dd/MM/yyyy HH:mm')
                                : '—'}
                            . Razón: {ticket.void_reason}
                        </AlertDescription>
                    </Alert>
                )}

                {/* Header */}
                <div className="flex items-start justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Link
                            href="/pos/tickets"
                            className="text-muted-foreground hover:text-foreground transition-colors"
                        >
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                        <div>
                            <div className="flex items-center gap-3">
                                <h1 className="text-2xl font-bold tracking-tight text-foreground">
                                    {ticket.ticket_number}
                                </h1>
                                <PosTicketStatusBadge status={ticket.status} />
                            </div>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {format(new Date(ticket.created_at), 'dd/MM/yyyy HH:mm')}
                            </p>
                        </div>
                    </div>

                    {can.void && ticket.status === 'paid' && (
                        ticket.ecf_ncf ? (
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <span className="inline-block cursor-not-allowed">
                                            <Button
                                                variant="destructive"
                                                disabled
                                                aria-disabled="true"
                                            >
                                                Anular Ticket
                                            </Button>
                                        </span>
                                    </TooltipTrigger>
                                    <TooltipContent side="left" className="max-w-xs text-xs">
                                        Tickets con e-CF emitido se anulan emitiendo Nota de Crédito tipo 34
                                        en el portal DGII. Ver detalle del e-CF para instrucciones.
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        ) : (
                            <Button
                                variant="destructive"
                                onClick={() => setShowVoidModal(true)}
                            >
                                Anular Ticket
                            </Button>
                        )
                    )}
                </div>

                {/* Info row */}
                <div className="grid gap-4 sm:grid-cols-3">
                    <div className="rounded-lg border border-border bg-card p-4">
                        <div className="flex items-center gap-2 text-muted-foreground mb-1">
                            <Calendar className="h-4 w-4" />
                            <span className="text-xs font-medium uppercase tracking-wide">Fecha</span>
                        </div>
                        <p className="text-sm font-medium text-foreground">
                            {format(new Date(ticket.created_at), 'dd/MM/yyyy HH:mm')}
                        </p>
                    </div>
                    <div className="rounded-lg border border-border bg-card p-4">
                        <div className="flex items-center gap-2 text-muted-foreground mb-1">
                            <User className="h-4 w-4" />
                            <span className="text-xs font-medium uppercase tracking-wide">Cliente</span>
                        </div>
                        <p className="text-sm font-medium text-foreground">
                            {ticket.client_name ?? (
                                <span className="italic text-muted-foreground">Walk-In</span>
                            )}
                        </p>
                        {ticket.client_rnc && (
                            <p className="text-xs text-muted-foreground">RNC: {ticket.client_rnc}</p>
                        )}
                    </div>
                    <div className="rounded-lg border border-border bg-card p-4">
                        <div className="flex items-center gap-2 text-muted-foreground mb-1">
                            <User className="h-4 w-4" />
                            <span className="text-xs font-medium uppercase tracking-wide">Cajero</span>
                        </div>
                        <p className="text-sm font-medium text-foreground">
                            {ticket.cashier?.name ?? '—'}
                        </p>
                    </div>
                </div>

                {/* Items */}
                <div className="rounded-lg border border-border bg-card overflow-hidden">
                    <div className="border-b border-border px-4 py-3">
                        <h2 className="font-semibold text-foreground">Items</h2>
                    </div>
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-border bg-muted/50">
                                <th className="px-4 py-2 text-left font-medium text-muted-foreground">Servicio / Producto</th>
                                <th className="px-4 py-2 text-center font-medium text-muted-foreground">Qty</th>
                                <th className="px-4 py-2 text-right font-medium text-muted-foreground">Precio Unit.</th>
                                <th className="px-4 py-2 text-right font-medium text-muted-foreground">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {ticket.items.map((item) => (
                                <tr key={item.id} className="border-b border-border last:border-0">
                                    <td className="px-4 py-3">
                                        <p className="font-medium text-foreground">{item.name}</p>
                                        {item.employee && (
                                            <p className="text-xs text-muted-foreground">
                                                {item.employee.user.name}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-center text-foreground">{item.qty}</td>
                                    <td className="px-4 py-3 text-right text-foreground">
                                        {formatCurrency(item.unit_price)}
                                    </td>
                                    <td className="px-4 py-3 text-right font-medium text-foreground">
                                        {formatCurrency(item.line_total)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Totals */}
                <div className="rounded-lg border border-border bg-card p-4 space-y-2">
                    <div className="flex justify-between text-sm">
                        <span className="text-muted-foreground">Subtotal</span>
                        <span className="text-foreground">{formatCurrency(ticket.subtotal)}</span>
                    </div>
                    {discount > 0 && (
                        <div className="flex justify-between text-sm">
                            <span className="text-muted-foreground">Descuento</span>
                            <span className="text-destructive">-{formatCurrency(ticket.discount_amount)}</span>
                        </div>
                    )}
                    <div className="flex justify-between text-sm">
                        <span className="text-muted-foreground">ITBIS ({ticket.itbis_pct}%)</span>
                        <span className="text-foreground">{formatCurrency(ticket.itbis_amount)}</span>
                    </div>
                    {tip > 0 && (
                        <div className="flex justify-between text-sm">
                            <span className="text-muted-foreground">Propina</span>
                            <span className="text-foreground">{formatCurrency(ticket.tip_amount)}</span>
                        </div>
                    )}
                    <div className="flex justify-between border-t border-border pt-2 font-bold text-base">
                        <span className="text-foreground">TOTAL</span>
                        <span className="text-foreground">{formatCurrency(ticket.total)}</span>
                    </div>
                </div>

                {/* Payments */}
                <div className="rounded-lg border border-border bg-card overflow-hidden">
                    <div className="border-b border-border px-4 py-3">
                        <h2 className="font-semibold text-foreground">Pagos</h2>
                    </div>
                    <div className="divide-y divide-border">
                        {ticket.payments.map((payment) => (
                            <div key={payment.id} className="flex items-start justify-between px-4 py-3">
                                <div className="flex items-center gap-2">
                                    {METHOD_ICONS[payment.method]}
                                    <div>
                                        <p className="text-sm font-medium text-foreground">
                                            {METHOD_LABELS[payment.method] ?? payment.method}
                                        </p>
                                        {payment.reference && (
                                            <p className="text-xs text-muted-foreground">
                                                Ref: {payment.reference}
                                            </p>
                                        )}
                                        {payment.method === 'cash' && payment.cash_tendered && (
                                            <p className="text-xs text-muted-foreground">
                                                Entregado: {formatCurrency(payment.cash_tendered)}
                                                {payment.cash_change && (
                                                    <> · Vuelto: {formatCurrency(payment.cash_change)}</>
                                                )}
                                            </p>
                                        )}
                                    </div>
                                </div>
                                <span className="text-sm font-medium text-foreground">
                                    {formatCurrency(payment.amount)}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>

                {/* e-CF */}
                {ticket.ecf_status !== 'na' && (
                    <div className="rounded-lg border border-border bg-card p-4 space-y-3">
                        <h2 className="font-semibold text-foreground">Comprobante Fiscal (e-CF)</h2>
                        <div className="flex flex-wrap items-center gap-3">
                            <EcfStatusBadge
                                status={ticket.ecf_status}
                                errorMessage={ticket.ecf_error_message ?? undefined}
                            />
                            {ticket.ecf_ncf && (
                                <Badge className="bg-secondary text-secondary-foreground">
                                    NCF: {ticket.ecf_ncf}
                                </Badge>
                            )}
                            {ticket.ecf_type && (
                                <span className="text-sm text-muted-foreground">
                                    {ticket.ecf_type === 'consumidor_final' ? 'Consumidor Final' : 'Crédito Fiscal'}
                                </span>
                            )}
                            {ticket.ecf_emitted_at && (
                                <span className="text-sm text-muted-foreground">
                                    Emitida: {format(new Date(ticket.ecf_emitted_at), 'dd/MM/yyyy HH:mm')}
                                </span>
                            )}
                        </div>
                        {ticket.ecf_status === 'error' && (
                            <div className="space-y-2">
                                {ticket.ecf_error_message && (
                                    <p className="text-sm text-destructive">{ticket.ecf_error_message}</p>
                                )}
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => {
                                        alert('No implementado en esta versión');
                                    }}
                                >
                                    Reintentar
                                </Button>
                            </div>
                        )}
                    </div>
                )}

                {/* Notes */}
                {ticket.notes && (
                    <div className="rounded-lg border border-border bg-card p-4">
                        <h2 className="mb-2 font-semibold text-foreground">Notas</h2>
                        <p className="text-sm text-muted-foreground">{ticket.notes}</p>
                    </div>
                )}

                {/* Appointment origin */}
                {ticket.appointment && (
                    <div className="rounded-lg border border-border bg-card p-4">
                        <h2 className="mb-2 font-semibold text-foreground">Cita de origen</h2>
                        <Link
                            href={`/appointments/${ticket.appointment.id}`}
                            className="text-sm text-primary hover:underline"
                        >
                            Ver cita #{ticket.appointment.id} —{' '}
                            {format(new Date(ticket.appointment.scheduled_at), 'dd/MM/yyyy HH:mm')} →
                        </Link>
                    </div>
                )}
            </div>

            {/* Void Modal */}
            <AlertDialog open={showVoidModal} onOpenChange={setShowVoidModal}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Anular Ticket {ticket.ticket_number}
                        </AlertDialogTitle>
                        <AlertDialogDescription asChild>
                            <div>
                                <p>
                                    Total: {formatCurrency(ticket.total)}
                                    {paymentMethodLabel ? ` · Método: ${paymentMethodLabel}` : ''}
                                </p>
                            </div>
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <div className="my-4 space-y-2">
                        <Label>Razón de anulación *</Label>
                        <Textarea
                            value={voidReason}
                            onChange={(e) => setVoidReason(e.target.value)}
                            placeholder="Explica el motivo de la anulación..."
                            rows={3}
                        />
                        {voidReason.length > 0 && voidReason.length < 10 && (
                            <p className="text-sm text-destructive">Mínimo 10 caracteres</p>
                        )}
                    </div>
                    <AlertDialogFooter>
                        <AlertDialogCancel onClick={() => setVoidReason('')}>
                            Cancelar
                        </AlertDialogCancel>
                        <AlertDialogAction
                            disabled={voidReason.length < 10 || processing}
                            onClick={handleVoid}
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        >
                            Anular Ticket
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
