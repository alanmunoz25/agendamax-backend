import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { AlertTriangle } from 'lucide-react';
import type { PayrollPeriod, PayrollRecord } from '@/types/models';

interface VoidConfirmationModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    record: PayrollRecord;
    nextPeriod: Pick<PayrollPeriod, 'id' | 'starts_on' | 'ends_on'> | null;
    onConfirm: (reason: string) => void;
    processing?: boolean;
}

export function VoidConfirmationModal({ open, onOpenChange, record, nextPeriod, onConfirm, processing }: VoidConfirmationModalProps) {
    const [reason, setReason] = useState('');

    const isPaid = record.status === 'paid';
    const employeeName = record.employee?.user?.name ?? 'Empleado';
    const gross = Number(record.gross_total).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
    const isReasonValid = reason.trim().length >= 10;

    const handleConfirm = () => {
        if (!isReasonValid) return;
        onConfirm(reason.trim());
    };

    const handleOpenChange = (value: boolean) => {
        if (!value) setReason('');
        onOpenChange(value);
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        {isPaid && <AlertTriangle className="h-5 w-5 text-destructive" />}
                        {isPaid ? 'Anular Pago Realizado' : 'Anular Record de Nómina'}
                    </DialogTitle>
                </DialogHeader>

                <div className="space-y-4 py-2">
                    <div className="text-sm space-y-1">
                        <p className="text-muted-foreground">Empleado: <span className="font-medium text-foreground">{employeeName}</span></p>
                        <p className="text-muted-foreground">
                            Estado actual: <span className="font-medium text-foreground uppercase">{record.status}</span>
                        </p>
                        <p className="text-muted-foreground">
                            {isPaid ? 'Bruto pagado:' : 'Bruto:'} <span className="font-semibold text-foreground">{gross}</span>
                        </p>
                        {isPaid && record.payment_method && (
                            <p className="text-muted-foreground">
                                Método de pago: <span className="font-medium text-foreground">{record.payment_method}</span>
                            </p>
                        )}
                    </div>

                    {isPaid && nextPeriod ? (
                        <div className="rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm space-y-1">
                            <p className="font-semibold text-destructive flex items-center gap-1">
                                <AlertTriangle className="h-4 w-4" /> ADVERTENCIA: Este pago ya fue desembolsado.
                            </p>
                            <p className="text-muted-foreground">Se creará automáticamente:</p>
                            <ul className="text-muted-foreground ml-4 list-disc space-y-0.5 text-xs">
                                <li>Ajuste DÉBITO en período siguiente</li>
                                <li>Período: {nextPeriod.starts_on} — {nextPeriod.ends_on}</li>
                                <li>Monto: -{gross}</li>
                                <li>Empleado: {employeeName}</li>
                            </ul>
                            <p className="text-xs text-muted-foreground mt-1">
                                Las comisiones del período anterior permanecen marcadas como pagadas.
                            </p>
                        </div>
                    ) : !isPaid ? (
                        <p className="text-sm text-muted-foreground rounded-md bg-muted/50 px-3 py-2">
                            ℹ️ Este record no ha sido pagado, por lo que no se generará un ajuste compensatorio.
                        </p>
                    ) : null}

                    <div className="space-y-1.5">
                        <Label>Razón de anulación *</Label>
                        <Textarea
                            placeholder="Explica por qué se anula este record..."
                            value={reason}
                            onChange={(e) => setReason(e.target.value)}
                            rows={3}
                            disabled={processing}
                        />
                        {reason.length > 0 && !isReasonValid && (
                            <p className="text-xs text-destructive">Mínimo 10 caracteres ({reason.length}/10)</p>
                        )}
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={() => handleOpenChange(false)} disabled={processing}>
                        Cancelar
                    </Button>
                    <Button
                        variant="destructive"
                        onClick={handleConfirm}
                        disabled={!isReasonValid || processing}
                    >
                        {processing
                            ? 'Anulando...'
                            : isPaid
                              ? 'Anular y Crear Débito ⚠'
                              : 'Anular Record'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
