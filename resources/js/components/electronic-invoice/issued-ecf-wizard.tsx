import { useState, useMemo } from 'react';
import { router } from '@inertiajs/react';
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
import { Separator } from '@/components/ui/separator';
import { AlertTriangle, Plus, Trash2, Loader, CheckCircle } from 'lucide-react';

interface Service {
    id: number;
    name: string;
    price: string;
}

interface LineItem {
    description: string;
    qty: string;
    unit_price: string;
    discount_pct: string;
}

interface PrefilledData {
    appointmentId?: number;
    clientRnc?: string;
    clientName?: string;
    lines?: Array<{
        description: string;
        qty: number;
        unit_price: string;
        discount_pct: string;
    }>;
}

interface IssuedEcfWizardProps {
    sequences: Record<string, { available: number }>;
    services: Service[];
    prefilledData?: PrefilledData;
    ambiente: string;
    onSuccess: (ecfId: number, ncf: string) => void;
    onCancel: () => void;
}

const ECF_TYPES = [
    { value: '31', label: '31 — Factura de Crédito Fiscal Electrónica' },
    { value: '32', label: '32 — Factura de Consumo Electrónica' },
    { value: '33', label: '33 — Nota de Débito Electrónica' },
    { value: '34', label: '34 — Nota de Crédito Electrónica' },
];

const TIPO_PAGO_OPTIONS = [
    { value: 'contado', label: 'Contado' },
    { value: 'credito', label: 'Crédito' },
];

function fmtDOP(amount: number): string {
    return amount.toLocaleString('es-DO', {
        style: 'currency',
        currency: 'DOP',
    });
}

function calcLineTotal(qty: string, unit_price: string, discount_pct: string): number {
    const q = parseFloat(qty) || 0;
    const p = parseFloat(unit_price) || 0;
    const d = parseFloat(discount_pct) || 0;
    return q * p * (1 - d / 100);
}

export function IssuedEcfWizard({
    sequences,
    services,
    prefilledData,
    ambiente,
    onSuccess,
    onCancel,
}: IssuedEcfWizardProps) {
    const [step, setStep] = useState<1 | 2 | 3>(1);
    const [serviceSearch, setServiceSearch] = useState('');

    const [step1Data, setStep1Data] = useState({
        tipo_ecf: '',
        tipo_pago: 'contado',
        client_rnc: prefilledData?.clientRnc ?? '',
        client_name: prefilledData?.clientName ?? '',
        client_direccion: '',
        ecf_referencia: '',
        indicador_monto_gravado: 1,
    });

    const [lines, setLines] = useState<LineItem[]>(
        prefilledData?.lines?.map((l) => ({
            description: l.description,
            qty: String(l.qty),
            unit_price: l.unit_price,
            discount_pct: l.discount_pct,
        })) ?? [{ description: '', qty: '1', unit_price: '', discount_pct: '0' }]
    );

    const [processing, setProcessing] = useState(false);

    const selectedSequence = step1Data.tipo_ecf
        ? sequences[step1Data.tipo_ecf]
        : null;
    const sequencesAvailable = selectedSequence?.available ?? 0;
    const noSequences = step1Data.tipo_ecf && sequencesAvailable === 0;

    const subtotal = useMemo(
        () =>
            lines.reduce(
                (sum, l) =>
                    sum +
                    calcLineTotal(l.qty, l.unit_price, l.discount_pct),
                0
            ),
        [lines]
    );

    const itbisRate = step1Data.indicador_monto_gravado === 1 ? 0.18 : 0;
    const montoGravado = step1Data.indicador_monto_gravado === 1
        ? subtotal / 1.18
        : 0;
    const itbis = montoGravado * itbisRate;

    const addLine = () => {
        setLines((prev) => [
            ...prev,
            { description: '', qty: '1', unit_price: '', discount_pct: '0' },
        ]);
    };

    const removeLine = (index: number) => {
        setLines((prev) => prev.filter((_, i) => i !== index));
    };

    const updateLine = (index: number, field: keyof LineItem, value: string) => {
        setLines((prev) =>
            prev.map((line, i) =>
                i === index ? { ...line, [field]: value } : line
            )
        );
    };

    const addServiceLine = (service: Service) => {
        setLines((prev) => [
            ...prev,
            {
                description: service.name,
                qty: '1',
                unit_price: String(service.price),
                discount_pct: '0',
            },
        ]);
        setServiceSearch('');
    };

    const filteredServices = services.filter((s) =>
        s.name.toLowerCase().includes(serviceSearch.toLowerCase())
    );

    const handleSubmit = () => {
        const formData = {
            tipo_ecf: step1Data.tipo_ecf,
            tipo_pago: step1Data.tipo_pago,
            client_rnc: step1Data.client_rnc || undefined,
            client_name: step1Data.client_name || undefined,
            client_direccion: step1Data.client_direccion || undefined,
            indicador_monto_gravado: step1Data.indicador_monto_gravado,
            ecf_referencia: step1Data.ecf_referencia || undefined,
            items: lines.map((l) => ({
                description: l.description,
                qty: parseFloat(l.qty) || 1,
                unit_price: l.unit_price,
                discount_pct: l.discount_pct || '0',
            })),
        };

        setProcessing(true);
        router.post('/admin/electronic-invoice/issued', formData, {
            onSuccess: () => {
                setProcessing(false);
                onSuccess(0, '');
            },
            onError: () => {
                setProcessing(false);
            },
            onFinish: () => {
                setProcessing(false);
            },
        });
    };

    const stepLabels = ['Tipo y Cliente', 'Líneas e Items', 'Revisión y Emisión'];

    return (
        <div className="space-y-6">
            {/* Progress bar */}
            <div className="flex items-center gap-2">
                {stepLabels.map((label, i) => {
                    const stepNum = (i + 1) as 1 | 2 | 3;
                    const isActive = step === stepNum;
                    const isDone = step > stepNum;

                    return (
                        <div key={label} className="flex items-center gap-2">
                            {i > 0 && (
                                <div
                                    className={`h-px flex-1 ${
                                        isDone
                                            ? 'bg-primary'
                                            : 'bg-border'
                                    }`}
                                />
                            )}
                            <button
                                type="button"
                                onClick={() => isDone && setStep(stepNum)}
                                className={`flex items-center gap-2 text-sm ${
                                    isActive
                                        ? 'font-semibold text-primary'
                                        : isDone
                                          ? 'cursor-pointer text-primary/70'
                                          : 'text-muted-foreground'
                                }`}
                            >
                                <span
                                    className={`flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold ${
                                        isActive
                                            ? 'bg-primary text-primary-foreground'
                                            : isDone
                                              ? 'bg-primary/20 text-primary'
                                              : 'bg-muted text-muted-foreground'
                                    }`}
                                >
                                    {stepNum}
                                </span>
                                <span className="hidden sm:inline">{label}</span>
                            </button>
                        </div>
                    );
                })}
            </div>

            <Separator />

            {/* Step 1 */}
            {step === 1 && (
                <div className="space-y-5">
                    <h3 className="text-base font-semibold">
                        Paso 1 — Tipo y Cliente
                    </h3>

                    {/* ECF Type */}
                    <div className="space-y-2">
                        <Label>Tipo de comprobante *</Label>
                        <Select
                            value={step1Data.tipo_ecf}
                            onValueChange={(v) =>
                                setStep1Data((p) => ({ ...p, tipo_ecf: v }))
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Seleccionar tipo..." />
                            </SelectTrigger>
                            <SelectContent>
                                {ECF_TYPES.map((t) => (
                                    <SelectItem key={t.value} value={t.value}>
                                        {t.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        {step1Data.tipo_ecf && (
                            <p className="text-xs text-muted-foreground">
                                Secuencias disponibles:{' '}
                                <span
                                    className={
                                        sequencesAvailable === 0
                                            ? 'font-semibold text-destructive'
                                            : sequencesAvailable <= 50
                                              ? 'font-semibold text-amber-600'
                                              : 'font-semibold text-[var(--color-green-brand)]'
                                    }
                                >
                                    {sequencesAvailable}
                                </span>
                            </p>
                        )}

                        {noSequences && (
                            <div className="flex items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">
                                <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                                <span>
                                    Sin secuencias disponibles para Tipo{' '}
                                    {step1Data.tipo_ecf}. Debes registrar más rangos en{' '}
                                    <a
                                        href="/admin/electronic-invoice/settings"
                                        className="underline"
                                    >
                                        Configuración
                                    </a>
                                    .
                                </span>
                            </div>
                        )}
                    </div>

                    {/* Tipo de pago */}
                    <div className="space-y-2">
                        <Label>Tipo de Pago *</Label>
                        <Select
                            value={step1Data.tipo_pago}
                            onValueChange={(v) =>
                                setStep1Data((p) => ({ ...p, tipo_pago: v }))
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {TIPO_PAGO_OPTIONS.map((o) => (
                                    <SelectItem key={o.value} value={o.value}>
                                        {o.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Client */}
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label>RNC / Cédula</Label>
                            <Input
                                value={step1Data.client_rnc}
                                onChange={(e) =>
                                    setStep1Data((p) => ({
                                        ...p,
                                        client_rnc: e.target.value,
                                    }))
                                }
                                placeholder="Ej. 132456789"
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Razón Social / Nombre</Label>
                            <Input
                                value={step1Data.client_name}
                                onChange={(e) =>
                                    setStep1Data((p) => ({
                                        ...p,
                                        client_name: e.target.value,
                                    }))
                                }
                                placeholder="Nombre del cliente"
                            />
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label>Dirección (opcional)</Label>
                        <Input
                            value={step1Data.client_direccion}
                            onChange={(e) =>
                                setStep1Data((p) => ({
                                    ...p,
                                    client_direccion: e.target.value,
                                }))
                            }
                            placeholder="Dirección del cliente"
                        />
                    </div>

                    {/* Nota de Crédito reference */}
                    {step1Data.tipo_ecf === '34' && (
                        <div className="space-y-2 rounded-md border border-border bg-muted/30 p-4">
                            <Label>NCF que se modifica (documento de referencia) *</Label>
                            <Input
                                value={step1Data.ecf_referencia}
                                onChange={(e) =>
                                    setStep1Data((p) => ({
                                        ...p,
                                        ecf_referencia: e.target.value,
                                    }))
                                }
                                placeholder="Ej. E310000000042"
                                className="font-mono"
                            />
                        </div>
                    )}

                    {/* Actions */}
                    <div className="flex justify-end gap-3">
                        <Button variant="outline" onClick={onCancel}>
                            Cancelar
                        </Button>
                        <Button
                            onClick={() => setStep(2)}
                            disabled={!step1Data.tipo_ecf || !!noSequences}
                        >
                            Siguiente: Items →
                        </Button>
                    </div>
                </div>
            )}

            {/* Step 2 */}
            {step === 2 && (
                <div className="space-y-5">
                    <h3 className="text-base font-semibold">
                        Paso 2 — Líneas e Items
                    </h3>

                    {/* Service autocomplete */}
                    <div className="flex gap-2">
                        <Input
                            placeholder="Buscar servicio existente..."
                            value={serviceSearch}
                            onChange={(e) => setServiceSearch(e.target.value)}
                            className="flex-1"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            onClick={addLine}
                        >
                            <Plus className="mr-2 h-4 w-4" />
                            Línea libre
                        </Button>
                    </div>

                    {serviceSearch && filteredServices.length > 0 && (
                        <div className="rounded-md border border-border bg-card shadow-sm">
                            {filteredServices.slice(0, 5).map((s) => (
                                <button
                                    key={s.id}
                                    type="button"
                                    onClick={() => addServiceLine(s)}
                                    className="flex w-full items-center justify-between px-3 py-2 text-sm hover:bg-muted"
                                >
                                    <span>{s.name}</span>
                                    <span className="text-muted-foreground">
                                        {fmtDOP(Number(s.price))}
                                    </span>
                                </button>
                            ))}
                        </div>
                    )}

                    {/* Lines table */}
                    <div className="overflow-x-auto rounded-md border border-border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50">
                                <tr>
                                    <th className="px-3 py-2 text-left font-medium text-muted-foreground">
                                        Descripción
                                    </th>
                                    <th className="px-3 py-2 text-right font-medium text-muted-foreground">
                                        Cant.
                                    </th>
                                    <th className="px-3 py-2 text-right font-medium text-muted-foreground">
                                        P. Unitario
                                    </th>
                                    <th className="px-3 py-2 text-right font-medium text-muted-foreground">
                                        Desc. %
                                    </th>
                                    <th className="px-3 py-2 text-right font-medium text-muted-foreground">
                                        Total
                                    </th>
                                    <th className="w-8 px-3 py-2" />
                                </tr>
                            </thead>
                            <tbody>
                                {lines.map((line, i) => (
                                    <tr
                                        key={i}
                                        className="border-t border-border"
                                    >
                                        <td className="px-3 py-2">
                                            <Input
                                                value={line.description}
                                                onChange={(e) =>
                                                    updateLine(
                                                        i,
                                                        'description',
                                                        e.target.value
                                                    )
                                                }
                                                className="h-8 text-xs"
                                                placeholder="Descripción"
                                            />
                                        </td>
                                        <td className="px-3 py-2">
                                            <Input
                                                type="number"
                                                value={line.qty}
                                                onChange={(e) =>
                                                    updateLine(
                                                        i,
                                                        'qty',
                                                        e.target.value
                                                    )
                                                }
                                                className="h-8 w-16 text-right text-xs"
                                                min="0.01"
                                                step="0.01"
                                            />
                                        </td>
                                        <td className="px-3 py-2">
                                            <Input
                                                type="number"
                                                value={line.unit_price}
                                                onChange={(e) =>
                                                    updateLine(
                                                        i,
                                                        'unit_price',
                                                        e.target.value
                                                    )
                                                }
                                                className="h-8 w-24 text-right text-xs"
                                                min="0"
                                                step="0.01"
                                                placeholder="0.00"
                                            />
                                        </td>
                                        <td className="px-3 py-2">
                                            <Input
                                                type="number"
                                                value={line.discount_pct}
                                                onChange={(e) =>
                                                    updateLine(
                                                        i,
                                                        'discount_pct',
                                                        e.target.value
                                                    )
                                                }
                                                className="h-8 w-16 text-right text-xs"
                                                min="0"
                                                max="100"
                                                step="0.01"
                                            />
                                        </td>
                                        <td className="px-3 py-2 text-right font-medium text-foreground">
                                            {fmtDOP(
                                                calcLineTotal(
                                                    line.qty,
                                                    line.unit_price,
                                                    line.discount_pct
                                                )
                                            )}
                                        </td>
                                        <td className="px-3 py-2">
                                            <button
                                                type="button"
                                                onClick={() => removeLine(i)}
                                                className="text-muted-foreground hover:text-destructive"
                                                disabled={lines.length === 1}
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot className="border-t border-border bg-muted/30">
                                <tr>
                                    <td
                                        colSpan={4}
                                        className="px-3 py-2 text-right text-sm font-medium text-muted-foreground"
                                    >
                                        Subtotal:
                                    </td>
                                    <td className="px-3 py-2 text-right font-semibold text-foreground">
                                        {fmtDOP(subtotal)}
                                    </td>
                                    <td />
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    {/* Indicador monto gravado */}
                    <div className="space-y-2">
                        <Label>Indicador de Monto Gravado *</Label>
                        <div className="flex gap-4">
                            {[
                                { value: 1, label: '1 — Monto gravado (servicios con ITBIS)' },
                                { value: 0, label: '0 — Monto no gravado (exento de ITBIS)' },
                            ].map((opt) => (
                                <label
                                    key={opt.value}
                                    className="flex cursor-pointer items-center gap-2 text-sm"
                                >
                                    <input
                                        type="radio"
                                        name="indicador"
                                        value={opt.value}
                                        checked={
                                            step1Data.indicador_monto_gravado ===
                                            opt.value
                                        }
                                        onChange={() =>
                                            setStep1Data((p) => ({
                                                ...p,
                                                indicador_monto_gravado:
                                                    opt.value,
                                            }))
                                        }
                                    />
                                    {opt.label}
                                </label>
                            ))}
                        </div>
                    </div>

                    <div className="flex justify-end gap-3">
                        <Button
                            variant="outline"
                            onClick={() => setStep(1)}
                        >
                            ← Anterior
                        </Button>
                        <Button
                            onClick={() => setStep(3)}
                            disabled={
                                lines.length === 0 ||
                                lines.some((l) => !l.description)
                            }
                        >
                            Siguiente: Revisión →
                        </Button>
                    </div>
                </div>
            )}

            {/* Step 3 */}
            {step === 3 && (
                <div className="space-y-5">
                    <h3 className="text-base font-semibold">
                        Paso 3 — Revisión y Emisión
                    </h3>

                    {/* Summary */}
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="rounded-md border border-border bg-muted/30 p-4 text-sm space-y-1">
                            <p>
                                <span className="text-muted-foreground">Tipo:</span>{' '}
                                <strong>
                                    {step1Data.tipo_ecf} —{' '}
                                    {ECF_TYPES.find(
                                        (t) => t.value === step1Data.tipo_ecf
                                    )?.label.split(' — ')[1]}
                                </strong>
                            </p>
                            <p>
                                <span className="text-muted-foreground">Ambiente:</span>{' '}
                                <strong>{ambiente}</strong>
                            </p>
                            <p>
                                <span className="text-muted-foreground">Tipo de pago:</span>{' '}
                                {step1Data.tipo_pago}
                            </p>
                        </div>
                        <div className="rounded-md border border-border bg-muted/30 p-4 text-sm space-y-1">
                            <p>
                                <span className="text-muted-foreground">Cliente:</span>{' '}
                                <strong>
                                    {step1Data.client_name || 'Cliente General'}
                                </strong>
                            </p>
                            {step1Data.client_rnc && (
                                <p>
                                    <span className="text-muted-foreground">RNC:</span>{' '}
                                    {step1Data.client_rnc}
                                </p>
                            )}
                        </div>
                    </div>

                    {/* Lines summary */}
                    <div className="space-y-1 rounded-md border border-border p-4">
                        {lines.map((l, i) => (
                            <div key={i} className="flex justify-between text-sm">
                                <span className="text-foreground">
                                    {l.description}{' '}
                                    <span className="text-muted-foreground">
                                        {l.qty} × {fmtDOP(parseFloat(l.unit_price) || 0)}
                                    </span>
                                </span>
                                <span className="font-medium">
                                    {fmtDOP(
                                        calcLineTotal(
                                            l.qty,
                                            l.unit_price,
                                            l.discount_pct
                                        )
                                    )}
                                </span>
                            </div>
                        ))}
                    </div>

                    {/* Totals */}
                    <div className="rounded-md border border-border p-4 space-y-2 text-sm">
                        <div className="flex justify-between text-muted-foreground">
                            <span>Subtotal bruto:</span>
                            <span>{fmtDOP(subtotal)}</span>
                        </div>
                        {step1Data.indicador_monto_gravado === 1 && (
                            <>
                                <div className="flex justify-between text-muted-foreground">
                                    <span>Monto gravado ITBIS:</span>
                                    <span>{fmtDOP(montoGravado)}</span>
                                </div>
                                <div className="flex justify-between text-muted-foreground">
                                    <span>ITBIS (18%):</span>
                                    <span>{fmtDOP(itbis)}</span>
                                </div>
                            </>
                        )}
                        <Separator />
                        <div className="flex justify-between text-base font-bold">
                            <span>TOTAL:</span>
                            <span>{fmtDOP(subtotal)}</span>
                        </div>
                    </div>

                    {ambiente !== 'ECF' && (
                        <div className="flex items-center gap-2 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-300">
                            <AlertTriangle className="h-4 w-4 shrink-0" />
                            Ambiente de pruebas: este e-CF NO tiene validez fiscal
                        </div>
                    )}

                    <div className="flex justify-end gap-3">
                        <Button
                            variant="outline"
                            onClick={() => setStep(2)}
                            disabled={processing}
                        >
                            ← Anterior
                        </Button>
                        <Button
                            onClick={handleSubmit}
                            disabled={processing}
                        >
                            {processing ? (
                                <>
                                    <Loader className="mr-2 h-4 w-4 animate-spin" />
                                    Enviando a DGII...
                                </>
                            ) : (
                                <>
                                    <CheckCircle className="mr-2 h-4 w-4" />
                                    Emitir y Enviar a DGII
                                </>
                            )}
                        </Button>
                    </div>

                    {processing && (
                        <div className="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800 dark:border-blue-800 dark:bg-blue-950/30 dark:text-blue-300">
                            <div className="flex items-center gap-2">
                                <Loader className="h-4 w-4 animate-spin" />
                                <span className="font-medium">
                                    Enviando a DGII...
                                </span>
                            </div>
                            <p className="mt-1 text-xs opacity-80">
                                Generando XML y firma digital. Esto puede tomar
                                entre 5 y 30 segundos.
                            </p>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
