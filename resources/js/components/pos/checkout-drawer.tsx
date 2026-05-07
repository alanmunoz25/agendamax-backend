import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Switch } from '@/components/ui/switch';
import { useForm } from '@inertiajs/react';
import { AlertCircle, CheckCircle, Minus, Plus, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

export interface CheckoutItem {
    id: number;
    type: 'service' | 'product';
    name: string;
    unit_price: string;
    qty: number;
    employee_id?: number | null;
    appointment_service_id?: number | null;
}

interface CheckoutDrawerProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    source: 'appointment' | 'walkin';
    appointmentId?: number | null;
    initialItems: CheckoutItem[];
    client?: { id: number; name: string; email?: string; phone?: string } | null;
    employee?: { id: number; user: { name: string } } | null;
    employees: Array<{ id: number; user: { name: string } }>;
    ecfEnabled: boolean;
    onSuccess: () => void;
}

type TipMode = '10' | '15' | '20' | 'custom' | 'none';
type PaymentMethod = 'cash' | 'card' | 'transfer' | 'mixed' | null;
type ItbisPct = '0' | '16' | '18';
type EcfType = 'consumidor_final' | 'credito_fiscal';

function fmt(amount: number): string {
    return 'RD$' + amount.toLocaleString('es-DO', { minimumFractionDigits: 2 });
}

export function CheckoutDrawer({
    open,
    onOpenChange,
    source,
    appointmentId,
    initialItems,
    client,
    employee,
    employees,
    ecfEnabled,
    onSuccess,
}: CheckoutDrawerProps) {
    const [items, setItems] = useState<CheckoutItem[]>(initialItems);

    // Sync items when initialItems changes after mount (e.g. service added while drawer was closed).
    // Using JSON.stringify prevents infinite loops from reference inequality on each parent render.
    useEffect(() => {
        setItems(initialItems);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [JSON.stringify(initialItems)]);

    const [discountAmount, setDiscountAmount] = useState('0.00');
    const [itbisPct, setItbisPct] = useState<ItbisPct>('18');
    const [tipMode, setTipMode] = useState<TipMode>('none');
    const [tipCustom, setTipCustom] = useState('0.00');
    const [paymentMethod, setPaymentMethod] = useState<PaymentMethod>(null);
    const [cashTendered, setCashTendered] = useState('');
    const [cardAmount, setCardAmount] = useState('');
    const [transferAmount, setTransferAmount] = useState('');
    const [cashAmountMixed, setCashAmountMixed] = useState('');
    const [cardReference, setCardReference] = useState('');
    const [transferReference, setTransferReference] = useState('');
    const [ecfRequested, setEcfRequested] = useState(ecfEnabled);
    const [ecfType, setEcfType] = useState<EcfType>('consumidor_final');
    const [clientName, setClientName] = useState(client?.name ?? '');
    const [employeeId, setEmployeeId] = useState<number | null>(employee?.id ?? null);
    const [notes, setNotes] = useState('');
    const [submitted, setSubmitted] = useState(false);

    const form = useForm<Record<string, unknown>>({});

    const { subtotal, discountClamped, itbisAmount, tipAmount, total, grandTotal } =
        useMemo(() => {
            const sub = items.reduce((sum, i) => sum + Number(i.unit_price) * i.qty, 0);
            const disc = Math.min(parseFloat(discountAmount) || 0, sub);
            const itbisBase = sub - disc;
            const itbis = itbisBase * (parseInt(itbisPct) / 100);
            const tot = sub - disc + itbis;
            const tip =
                tipMode === '10'
                    ? tot * 0.1
                    : tipMode === '15'
                      ? tot * 0.15
                      : tipMode === '20'
                        ? tot * 0.2
                        : tipMode === 'custom'
                          ? parseFloat(tipCustom) || 0
                          : 0;
            return {
                subtotal: sub,
                discountClamped: disc,
                itbisAmount: itbis,
                tipAmount: tip,
                total: tot,
                grandTotal: tot + tip,
            };
        }, [items, discountAmount, itbisPct, tipMode, tipCustom]);

    const cashChange = useMemo(() => {
        const tendered = parseFloat(cashTendered) || 0;
        return tendered - grandTotal;
    }, [cashTendered, grandTotal]);

    const mixedSum = useMemo(() => {
        return (
            (parseFloat(cashAmountMixed) || 0) +
            (parseFloat(cardAmount) || 0) +
            (parseFloat(transferAmount) || 0)
        );
    }, [cashAmountMixed, cardAmount, transferAmount]);

    const isSubmitDisabled = useMemo(() => {
        if (items.length === 0) return true;
        if (!paymentMethod) return true;
        if (paymentMethod === 'cash' && (parseFloat(cashTendered) || 0) < grandTotal) return true;
        if (paymentMethod === 'mixed' && mixedSum < grandTotal) return true;
        if (form.processing) return true;
        return false;
    }, [items, paymentMethod, cashTendered, grandTotal, mixedSum, form.processing]);

    function buildPayments(): Array<{
        method: string;
        amount: string;
        reference?: string | null;
    }> {
        if (paymentMethod === 'cash') {
            return [{ method: 'cash', amount: grandTotal.toFixed(2) }];
        }
        if (paymentMethod === 'card') {
            return [
                {
                    method: 'card',
                    amount: grandTotal.toFixed(2),
                    reference: cardReference || null,
                },
            ];
        }
        if (paymentMethod === 'transfer') {
            return [
                {
                    method: 'transfer',
                    amount: grandTotal.toFixed(2),
                    reference: transferReference || null,
                },
            ];
        }
        const payments = [];
        if (parseFloat(cashAmountMixed) > 0) {
            payments.push({ method: 'cash', amount: (parseFloat(cashAmountMixed) || 0).toFixed(2) });
        }
        if (parseFloat(cardAmount) > 0) {
            payments.push({
                method: 'card',
                amount: (parseFloat(cardAmount) || 0).toFixed(2),
                reference: cardReference || null,
            });
        }
        if (parseFloat(transferAmount) > 0) {
            payments.push({
                method: 'transfer',
                amount: (parseFloat(transferAmount) || 0).toFixed(2),
                reference: transferReference || null,
            });
        }
        return payments;
    }

    function handleSubmit() {
        const data = {
            appointment_id: appointmentId ?? null,
            client_id: client?.id ?? null,
            client_name: clientName || null,
            employee_id: employeeId,
            items: items.map((item) => ({
                type: item.type,
                item_id: item.id,
                name: item.name,
                unit_price: item.unit_price,
                qty: item.qty,
                employee_id: item.employee_id ?? employeeId,
                appointment_service_id: item.appointment_service_id ?? null,
            })),
            discount_amount: discountClamped.toFixed(2),
            itbis_pct: itbisPct,
            tip_amount: tipAmount.toFixed(2),
            payments: buildPayments(),
            ecf_requested: ecfRequested,
            ecf_type: ecfRequested ? ecfType : null,
            notes: notes || null,
        };

        form.transform(() => data);
        form.post('/pos/tickets', {
            onSuccess: () => setSubmitted(true),
        });
    }

    function resetState() {
        setItems(initialItems);
        setDiscountAmount('0.00');
        setItbisPct('18');
        setTipMode('none');
        setTipCustom('0.00');
        setPaymentMethod(null);
        setCashTendered('');
        setCardAmount('');
        setTransferAmount('');
        setCashAmountMixed('');
        setCardReference('');
        setTransferReference('');
        setEcfRequested(ecfEnabled);
        setEcfType('consumidor_final');
        setClientName(client?.name ?? '');
        setEmployeeId(employee?.id ?? null);
        setNotes('');
        setSubmitted(false);
        form.reset();
    }

    const tabClass = (active: boolean) =>
        `flex-1 rounded-md py-1.5 text-xs font-medium transition-colors ${
            active
                ? 'bg-primary text-primary-foreground'
                : 'bg-muted text-muted-foreground hover:text-foreground'
        }`;

    const tipBtnClass = (active: boolean) =>
        `flex-1 rounded-md border py-1.5 text-xs font-medium transition-colors ${
            active
                ? 'border-[var(--color-blue-brand)] bg-[var(--color-blue-brand)]/10 text-[var(--color-blue-brand)]'
                : 'border-border bg-background text-foreground hover:bg-accent'
        }`;

    if (submitted) {
        return (
            <Sheet open={open} onOpenChange={onOpenChange}>
                <SheetContent className="w-full sm:w-[520px] sm:max-w-[520px] overflow-y-auto">
                    <div className="flex h-full flex-col items-center justify-center gap-4 p-8 text-center">
                        <CheckCircle className="size-16 text-[var(--color-green-brand)]" />
                        <h2 className="text-2xl font-bold text-foreground">Cobro exitoso</h2>
                        <p className="text-muted-foreground">Ticket registrado correctamente</p>
                        {ecfRequested && (
                            <p className="text-sm text-muted-foreground">
                                e-CF: procesándose en segundo plano...
                            </p>
                        )}
                        <div className="flex gap-3 mt-4">
                            <Button variant="outline" onClick={resetState}>
                                Nuevo cobro
                            </Button>
                            <Button
                                onClick={() => {
                                    onSuccess();
                                    onOpenChange(false);
                                }}
                            >
                                Cerrar
                            </Button>
                        </div>
                    </div>
                </SheetContent>
            </Sheet>
        );
    }

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full sm:w-[520px] sm:max-w-[520px] overflow-y-auto flex flex-col gap-0 p-0">
                <SheetHeader className="border-b border-border px-4 py-3">
                    <SheetTitle>Checkout</SheetTitle>
                </SheetHeader>

                <div className="flex-1 overflow-y-auto space-y-5 p-4">
                    {/* Client / Employee */}
                    <div className="space-y-2">
                        {source === 'appointment' ? (
                            <div className="flex items-center justify-between rounded-md bg-muted/50 p-3 text-sm">
                                <span className="font-medium text-foreground">
                                    {client?.name ?? 'Sin cliente'}
                                </span>
                                {employee && (
                                    <span className="text-muted-foreground">
                                        {employee.user.name}
                                    </span>
                                )}
                            </div>
                        ) : (
                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-1">
                                    <Label className="text-xs">Cliente (opcional)</Label>
                                    <Input
                                        placeholder="Nombre del cliente"
                                        value={clientName}
                                        onChange={(e) => setClientName(e.target.value)}
                                        className="h-8 text-sm"
                                    />
                                </div>
                                <div className="space-y-1">
                                    <Label className="text-xs">Empleado</Label>
                                    <Select
                                        value={employeeId?.toString() ?? ''}
                                        onValueChange={(v) =>
                                            setEmployeeId(v ? parseInt(v) : null)
                                        }
                                    >
                                        <SelectTrigger className="h-8 text-sm">
                                            <SelectValue placeholder="Seleccionar" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {employees.map((emp) => (
                                                <SelectItem
                                                    key={emp.id}
                                                    value={emp.id.toString()}
                                                >
                                                    {emp.user.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Items */}
                    <div className="space-y-2">
                        <Label className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            Items
                        </Label>
                        {items.length === 0 && (
                            <p className="text-sm text-muted-foreground">Sin items</p>
                        )}
                        {items.map((item, idx) => (
                            <div
                                key={`${item.type}-${item.id}-${idx}`}
                                className="flex items-center gap-2"
                            >
                                <div className="flex-1 min-w-0">
                                    <p className="truncate text-sm font-medium text-foreground">
                                        {item.name}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {fmt(Number(item.unit_price))} ×{' '}
                                        {fmt(Number(item.unit_price) * item.qty)}
                                    </p>
                                </div>
                                <div className="flex items-center gap-1 shrink-0">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="h-6 w-6 p-0"
                                        onClick={() => {
                                            if (item.qty <= 1) {
                                                setItems((prev) =>
                                                    prev.filter((_, i) => i !== idx),
                                                );
                                            } else {
                                                setItems((prev) =>
                                                    prev.map((it, i) =>
                                                        i === idx
                                                            ? { ...it, qty: it.qty - 1 }
                                                            : it,
                                                    ),
                                                );
                                            }
                                        }}
                                    >
                                        <Minus className="size-3" />
                                    </Button>
                                    <span className="min-w-[1.5rem] text-center text-sm">
                                        {item.qty}
                                    </span>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="h-6 w-6 p-0"
                                        onClick={() =>
                                            setItems((prev) =>
                                                prev.map((it, i) =>
                                                    i === idx ? { ...it, qty: it.qty + 1 } : it,
                                                ),
                                            )
                                        }
                                    >
                                        <Plus className="size-3" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="h-6 w-6 p-0 text-muted-foreground hover:text-destructive"
                                        onClick={() =>
                                            setItems((prev) => prev.filter((_, i) => i !== idx))
                                        }
                                    >
                                        <X className="size-3" />
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </div>

                    {/* Totals */}
                    <div className="space-y-2 rounded-md bg-muted/30 p-3">
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-muted-foreground">Subtotal</span>
                            <span>{fmt(subtotal)}</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="shrink-0 text-sm text-muted-foreground">
                                Descuento
                            </span>
                            <Input
                                type="text"
                                value={discountAmount}
                                onChange={(e) => setDiscountAmount(e.target.value)}
                                className="h-7 text-right text-sm"
                            />
                            {parseFloat(discountAmount) > subtotal && (
                                <Badge className="bg-[var(--color-amber-brand)]/10 text-[var(--color-amber-brand)] shrink-0 text-xs">
                                    Ajustado al máximo
                                </Badge>
                            )}
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="shrink-0 text-sm text-muted-foreground">ITBIS</span>
                            <Select
                                value={itbisPct}
                                onValueChange={(v) => setItbisPct(v as ItbisPct)}
                            >
                                <SelectTrigger className="h-7 w-24 text-sm">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="0">0%</SelectItem>
                                    <SelectItem value="16">16%</SelectItem>
                                    <SelectItem value="18">18%</SelectItem>
                                </SelectContent>
                            </Select>
                            <span className="ml-auto text-sm">{fmt(itbisAmount)}</span>
                        </div>
                        <div className="flex items-center justify-between border-t border-border pt-2 font-bold">
                            <span>Total</span>
                            <span className="text-lg">{fmt(total)}</span>
                        </div>
                    </div>

                    {/* Tip */}
                    <div className="space-y-2">
                        <Label className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            Propina
                        </Label>
                        <div className="flex gap-1.5">
                            {(['10', '15', '20', 'none'] as TipMode[]).map((mode) => (
                                <button
                                    key={mode}
                                    onClick={() => setTipMode(mode)}
                                    className={tipBtnClass(tipMode === mode)}
                                >
                                    {mode === 'none' ? 'Ninguna' : `${mode}%`}
                                </button>
                            ))}
                            <button
                                onClick={() => setTipMode('custom')}
                                className={tipBtnClass(tipMode === 'custom')}
                            >
                                Otro
                            </button>
                        </div>
                        {tipMode === 'custom' && (
                            <Input
                                type="text"
                                placeholder="0.00"
                                value={tipCustom}
                                onChange={(e) => setTipCustom(e.target.value)}
                                className="h-8 text-sm"
                            />
                        )}
                        {tipAmount > 0 && (
                            <p className="text-sm font-medium text-foreground">
                                Total con propina: {fmt(grandTotal)}
                            </p>
                        )}
                    </div>

                    {/* Payment method */}
                    <div className="space-y-3">
                        <Label className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            Método de pago
                        </Label>
                        <div className="flex gap-1 rounded-md bg-muted p-1">
                            {(['cash', 'card', 'transfer', 'mixed'] as PaymentMethod[]).map(
                                (method) => (
                                    <button
                                        key={method!}
                                        onClick={() => setPaymentMethod(method)}
                                        className={tabClass(paymentMethod === method)}
                                    >
                                        {method === 'cash'
                                            ? 'Efectivo'
                                            : method === 'card'
                                              ? 'Tarjeta'
                                              : method === 'transfer'
                                                ? 'Transferencia'
                                                : 'Mixto'}
                                    </button>
                                ),
                            )}
                        </div>

                        {paymentMethod === 'cash' && (
                            <div className="space-y-2">
                                <div className="space-y-1">
                                    <Label className="text-xs">Monto entregado</Label>
                                    <Input
                                        type="text"
                                        placeholder="0.00"
                                        value={cashTendered}
                                        onChange={(e) => setCashTendered(e.target.value)}
                                        className="h-8 text-sm"
                                    />
                                </div>
                                {cashTendered && (
                                    <p
                                        className={`text-sm font-medium ${cashChange >= 0 ? 'text-[var(--color-green-brand)]' : 'text-destructive'}`}
                                    >
                                        Vuelto: {fmt(cashChange)}
                                    </p>
                                )}
                            </div>
                        )}

                        {paymentMethod === 'card' && (
                            <div className="space-y-1">
                                <Label className="text-xs">Referencia (opcional)</Label>
                                <Input
                                    placeholder="Número de aprobación"
                                    value={cardReference}
                                    onChange={(e) => setCardReference(e.target.value)}
                                    className="h-8 text-sm"
                                />
                            </div>
                        )}

                        {paymentMethod === 'transfer' && (
                            <div className="space-y-1">
                                <Label className="text-xs">Referencia (opcional)</Label>
                                <Input
                                    placeholder="Número de transferencia"
                                    value={transferReference}
                                    onChange={(e) => setTransferReference(e.target.value)}
                                    className="h-8 text-sm"
                                />
                            </div>
                        )}

                        {paymentMethod === 'mixed' && (
                            <div className="space-y-2">
                                <div className="grid grid-cols-3 gap-2">
                                    <div className="space-y-1">
                                        <Label className="text-xs">Efectivo</Label>
                                        <Input
                                            type="text"
                                            placeholder="0.00"
                                            value={cashAmountMixed}
                                            onChange={(e) => setCashAmountMixed(e.target.value)}
                                            className="h-8 text-sm"
                                        />
                                    </div>
                                    <div className="space-y-1">
                                        <Label className="text-xs">Tarjeta</Label>
                                        <Input
                                            type="text"
                                            placeholder="0.00"
                                            value={cardAmount}
                                            onChange={(e) => setCardAmount(e.target.value)}
                                            className="h-8 text-sm"
                                        />
                                    </div>
                                    <div className="space-y-1">
                                        <Label className="text-xs">Transferencia</Label>
                                        <Input
                                            type="text"
                                            placeholder="0.00"
                                            value={transferAmount}
                                            onChange={(e) => setTransferAmount(e.target.value)}
                                            className="h-8 text-sm"
                                        />
                                    </div>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="text-xs text-muted-foreground">
                                        Suma: {fmt(mixedSum)}
                                    </span>
                                    {mixedSum < grandTotal ? (
                                        <Badge className="bg-destructive/10 text-destructive text-xs">
                                            Falta {fmt(grandTotal - mixedSum)}
                                        </Badge>
                                    ) : (
                                        <Badge className="bg-[var(--color-green-brand)]/10 text-[var(--color-green-brand)] text-xs">
                                            OK
                                        </Badge>
                                    )}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* e-CF section */}
                    {ecfEnabled && (
                        <div className="space-y-3 rounded-md border border-border p-3">
                            <div className="flex items-center justify-between">
                                <Label className="text-sm font-medium">
                                    Comprobante Fiscal (e-CF)
                                </Label>
                                <Switch
                                    checked={ecfRequested}
                                    onCheckedChange={setEcfRequested}
                                />
                            </div>
                            {ecfRequested && (
                                <div className="space-y-2">
                                    <div className="flex gap-3">
                                        <label className="flex items-center gap-2 cursor-pointer">
                                            <input
                                                type="radio"
                                                name="ecfType"
                                                value="consumidor_final"
                                                checked={ecfType === 'consumidor_final'}
                                                onChange={() => setEcfType('consumidor_final')}
                                                className="accent-[var(--color-blue-brand)]"
                                            />
                                            <span className="text-sm">Consumidor Final</span>
                                        </label>
                                        <label className="flex items-center gap-2 cursor-pointer">
                                            <input
                                                type="radio"
                                                name="ecfType"
                                                value="credito_fiscal"
                                                checked={ecfType === 'credito_fiscal'}
                                                onChange={() => setEcfType('credito_fiscal')}
                                                className="accent-[var(--color-blue-brand)]"
                                            />
                                            <span className="text-sm">Crédito Fiscal</span>
                                        </label>
                                    </div>
                                    {!client?.name && (
                                        <div className="space-y-1">
                                            <Label className="text-xs">
                                                Nombre del cliente para e-CF
                                            </Label>
                                            <Input
                                                placeholder="Nombre"
                                                value={clientName}
                                                onChange={(e) => setClientName(e.target.value)}
                                                className="h-8 text-sm"
                                            />
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    )}

                    {/* Notes */}
                    <div className="space-y-1">
                        <Label className="text-xs">Notas (opcional)</Label>
                        <Input
                            placeholder="Observaciones del cobro"
                            value={notes}
                            onChange={(e) => setNotes(e.target.value)}
                            className="h-8 text-sm"
                        />
                    </div>

                    {/* Error display */}
                    {form.errors.appointment_id && (
                        <Alert variant="destructive">
                            <AlertCircle className="size-4" />
                            <AlertDescription>{form.errors.appointment_id}</AlertDescription>
                        </Alert>
                    )}
                    {Object.keys(form.errors).length > 0 && !form.errors.appointment_id && (
                        <Alert variant="destructive">
                            <AlertCircle className="size-4" />
                            <AlertDescription>
                                {Object.values(form.errors)[0] as string}
                            </AlertDescription>
                        </Alert>
                    )}
                </div>

                <SheetFooter className="border-t border-border px-4 py-3 flex-row justify-between gap-2">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => onOpenChange(false)}
                    >
                        Cancelar
                    </Button>
                    <Button
                        size="sm"
                        onClick={handleSubmit}
                        disabled={isSubmitDisabled}
                        className="min-w-[8rem]"
                    >
                        {form.processing ? 'Procesando...' : `COBRAR ${fmt(grandTotal)}`}
                    </Button>
                </SheetFooter>
            </SheetContent>
        </Sheet>
    );
}
