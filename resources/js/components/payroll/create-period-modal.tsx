import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { DatePicker } from '@/components/ui/date-picker';
import { Label } from '@/components/ui/label';
import { AlertTriangle } from 'lucide-react';

interface CreatePeriodModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onConfirm: (start: string, end: string) => void;
    processing?: boolean;
    errors?: { start?: string; end?: string };
}

export function CreatePeriodModal({ open, onOpenChange, onConfirm, processing, errors }: CreatePeriodModalProps) {
    const [start, setStart] = useState('');
    const [end, setEnd] = useState('');

    const isValid = start && end && end > start;

    const handleConfirm = () => {
        if (!isValid) return;
        onConfirm(start, end);
    };

    const handleOpenChange = (value: boolean) => {
        if (!value) {
            setStart('');
            setEnd('');
        }
        onOpenChange(value);
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Crear Período de Nómina</DialogTitle>
                </DialogHeader>

                <div className="space-y-4 py-2">
                    <div className="space-y-1.5">
                        <Label htmlFor="start-date">Fecha de inicio *</Label>
                        <DatePicker value={start} onChange={setStart} disabled={processing} />
                        {errors?.start && (
                            <p className="text-sm text-destructive">{errors.start}</p>
                        )}
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="end-date">Fecha de fin *</Label>
                        <DatePicker value={end} onChange={setEnd} min={start || undefined} disabled={processing} />
                        {errors?.end && (
                            <p className="text-sm text-destructive">{errors.end}</p>
                        )}
                    </div>

                    <p className="flex items-start gap-1.5 text-xs text-muted-foreground">
                        <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                        Verifica que las fechas no se solapen con períodos existentes.
                    </p>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={() => handleOpenChange(false)} disabled={processing}>
                        Cancelar
                    </Button>
                    <Button onClick={handleConfirm} disabled={!isValid || processing}>
                        {processing ? 'Creando...' : 'Crear Período'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
