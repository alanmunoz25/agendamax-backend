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
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { PayrollRecord } from '@/types/models';

const PAYMENT_METHODS: { value: string; label: string }[] = [
    { value: 'cash', label: 'Efectivo' },
    { value: 'bank_transfer', label: 'Transferencia bancaria' },
    { value: 'check', label: 'Cheque' },
    { value: 'digital_wallet', label: 'Billetera digital' },
    { value: 'other', label: 'Otro' },
];

interface MarkPaidModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    record: PayrollRecord;
    onConfirm: (paymentMethod: string, paymentReference: string) => void;
    processing?: boolean;
}

export function MarkPaidModal({ open, onOpenChange, record, onConfirm, processing }: MarkPaidModalProps) {
    const [paymentMethod, setPaymentMethod] = useState('');
    const [paymentReference, setPaymentReference] = useState('');

    const employeeName = record.employee?.user?.name ?? 'Empleado';
    const gross = Number(record.gross_total).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });

    const handleConfirm = () => {
        if (!paymentMethod) return;
        onConfirm(paymentMethod, paymentReference);
    };

    const handleOpenChange = (value: boolean) => {
        if (!value) {
            setPaymentMethod('');
            setPaymentReference('');
        }
        onOpenChange(value);
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Marcar como Pagado</DialogTitle>
                </DialogHeader>

                <div className="space-y-4 py-2">
                    <div className="text-sm">
                        <p className="text-muted-foreground">Empleado: <span className="font-medium text-foreground">{employeeName}</span></p>
                        <p className="text-muted-foreground">Bruto a pagar: <span className="font-semibold text-foreground">{gross}</span></p>
                    </div>

                    <div className="space-y-1.5">
                        <Label>Método de pago *</Label>
                        <Select value={paymentMethod} onValueChange={setPaymentMethod} disabled={processing}>
                            <SelectTrigger>
                                <SelectValue placeholder="Selecciona un método..." />
                            </SelectTrigger>
                            <SelectContent>
                                {PAYMENT_METHODS.map((m) => (
                                    <SelectItem key={m.value} value={m.value}>{m.label}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-1.5">
                        <Label>Referencia / Número de operación</Label>
                        <Input
                            placeholder="ej. TXN-123456"
                            value={paymentReference}
                            onChange={(e) => setPaymentReference(e.target.value)}
                            disabled={processing}
                        />
                    </div>

                    <Separator />

                    <p className="text-xs text-muted-foreground">
                        Al confirmar, las comisiones del empleado quedarán marcadas como pagadas y no podrán modificarse.
                    </p>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={() => handleOpenChange(false)} disabled={processing}>
                        Cancelar
                    </Button>
                    <Button onClick={handleConfirm} disabled={!paymentMethod || processing}>
                        {processing ? 'Procesando...' : 'Confirmar Pago ✓'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
