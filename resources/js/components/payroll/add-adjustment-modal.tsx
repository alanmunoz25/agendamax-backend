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
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { AlertTriangle } from 'lucide-react';
import type { Employee, PayrollPeriod } from '@/types/models';

export interface AdjustmentFormData {
    employee_id: number;
    type: 'credit' | 'debit';
    amount: number;
    reason: string;
}

interface AddAdjustmentModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    period: PayrollPeriod;
    employees: Employee[];
    preselectedEmployee?: Employee;
    onConfirm: (data: AdjustmentFormData) => void;
    processing?: boolean;
    errors?: { employee_id?: string };
}

export function AddAdjustmentModal({
    open,
    onOpenChange,
    period,
    employees,
    preselectedEmployee,
    onConfirm,
    processing,
    errors,
}: AddAdjustmentModalProps) {
    const [employeeId, setEmployeeId] = useState<string>(preselectedEmployee ? String(preselectedEmployee.id) : '');
    const [type, setType] = useState<'credit' | 'debit'>('credit');
    const [amount, setAmount] = useState('');
    const [reason, setReason] = useState('');

    const isValid = employeeId && amount && Number(amount) > 0 && reason.trim().length >= 3;

    const handleConfirm = () => {
        if (!isValid) return;
        onConfirm({ employee_id: Number(employeeId), type, amount: Number(amount), reason: reason.trim() });
    };

    const handleOpenChange = (value: boolean) => {
        if (!value) {
            setEmployeeId(preselectedEmployee ? String(preselectedEmployee.id) : '');
            setType('credit');
            setAmount('');
            setReason('');
        }
        onOpenChange(value);
    };

    const periodLabel = `${period.starts_on} — ${period.ends_on}`;

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Agregar Ajuste Manual</DialogTitle>
                </DialogHeader>

                <div className="space-y-4 py-2">
                    <p className="text-sm text-muted-foreground">Período: <span className="font-medium text-foreground">{periodLabel}</span></p>

                    {errors?.employee_id && (
                        <div className="flex items-start gap-2 rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">
                            <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                            {errors.employee_id}
                        </div>
                    )}

                    <div className="space-y-1.5">
                        <Label>Empleado *</Label>
                        <Select value={employeeId} onValueChange={setEmployeeId} disabled={processing || !!preselectedEmployee}>
                            <SelectTrigger>
                                <SelectValue placeholder="Selecciona un empleado..." />
                            </SelectTrigger>
                            <SelectContent>
                                {employees.map((emp) => (
                                    <SelectItem key={emp.id} value={String(emp.id)}>
                                        {emp.user?.name ?? `Empleado #${emp.id}`}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-1.5">
                        <Label>Tipo de ajuste *</Label>
                        <div className="flex gap-4 text-sm">
                            <label className="flex items-center gap-1.5 cursor-pointer">
                                <input
                                    type="radio"
                                    name="adj-type"
                                    checked={type === 'credit'}
                                    onChange={() => setType('credit')}
                                    disabled={processing}
                                />
                                Crédito (suma al bruto)
                            </label>
                            <label className="flex items-center gap-1.5 cursor-pointer">
                                <input
                                    type="radio"
                                    name="adj-type"
                                    checked={type === 'debit'}
                                    onChange={() => setType('debit')}
                                    disabled={processing}
                                />
                                Débito (resta al bruto)
                            </label>
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <Label>Monto *</Label>
                        <Input
                            type="number"
                            step="0.01"
                            min="0.01"
                            placeholder="0.00"
                            value={amount}
                            onChange={(e) => setAmount(e.target.value)}
                            disabled={processing}
                        />
                    </div>

                    <div className="space-y-1.5">
                        <Label>Razón *</Label>
                        <Textarea
                            placeholder="Explica el ajuste..."
                            value={reason}
                            onChange={(e) => setReason(e.target.value)}
                            rows={2}
                            disabled={processing}
                        />
                    </div>

                    <p className="flex items-start gap-1 text-xs text-muted-foreground">
                        <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                        Solo aplicable si el record del empleado está en estado DRAFT.
                    </p>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={() => handleOpenChange(false)} disabled={processing}>
                        Cancelar
                    </Button>
                    <Button onClick={handleConfirm} disabled={!isValid || processing}>
                        {processing ? 'Guardando...' : 'Agregar Ajuste'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
