import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Form } from '@inertiajs/react';
import { useState } from 'react';

type ScopeType = 'global' | 'per_service' | 'per_employee' | 'specific';

interface CommissionRule {
    id: number;
    scope_type: ScopeType;
    employee: { id: number; name: string | null } | null;
    service: { id: number; name: string } | null;
    type: 'percentage' | 'fixed';
    value: string;
    is_active: boolean;
    effective_from: string | null;
    effective_until: string | null;
}

interface Employee {
    id: number;
    name: string;
}

interface Service {
    id: number;
    name: string;
}

interface CommissionRuleFormModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    rule?: CommissionRule | null;
    employees: Employee[];
    services: Service[];
    storeUrl: string;
    updateUrl?: string;
}

const SCOPE_LABELS: Record<ScopeType, string> = {
    global: 'Global (todos los empleados y servicios)',
    per_service: 'Por Servicio',
    per_employee: 'Por Empleado',
    specific: 'Específica (empleado + servicio)',
};

const PRIORITY_LABELS: Record<ScopeType, string> = {
    global: 'Prioridad 1 — más baja',
    per_service: 'Prioridad 2',
    per_employee: 'Prioridad 3',
    specific: 'Prioridad 4 — más alta',
};

export function CommissionRuleFormModal({ open, onOpenChange, rule, employees, services, storeUrl, updateUrl }: CommissionRuleFormModalProps) {
    const [scopeType, setScopeType] = useState<ScopeType>(rule?.scope_type ?? 'global');
    const isEditing = !!rule;
    const actionUrl = isEditing && updateUrl ? updateUrl : storeUrl;
    const method = isEditing ? 'put' : 'post';

    const showEmployee = scopeType === 'per_employee' || scopeType === 'specific';
    const showService = scopeType === 'per_service' || scopeType === 'specific';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{isEditing ? 'Editar Regla de Comisión' : 'Nueva Regla de Comisión'}</DialogTitle>
                </DialogHeader>

                <Form
                    action={actionUrl}
                    method={method}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="space-y-4 py-4">
                                {/* Scope */}
                                <div className="space-y-2">
                                    <Label>Alcance</Label>
                                    <Select name="scope_type" value={scopeType} onValueChange={(v) => setScopeType(v as ScopeType)}>
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {(Object.keys(SCOPE_LABELS) as ScopeType[]).map((s) => (
                                                <SelectItem key={s} value={s}>
                                                    {SCOPE_LABELS[s]}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <p className="text-xs text-muted-foreground">{PRIORITY_LABELS[scopeType]}</p>
                                </div>

                                {/* Employee selector */}
                                {showEmployee && (
                                    <div className="space-y-2">
                                        <Label>Empleado</Label>
                                        <Select name="employee_id" defaultValue={rule?.employee?.id?.toString()}>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Seleccionar empleado" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {employees.map((e) => (
                                                    <SelectItem key={e.id} value={String(e.id)}>
                                                        {e.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {errors.employee_id && <p className="text-sm text-destructive">{errors.employee_id}</p>}
                                    </div>
                                )}

                                {/* Service selector */}
                                {showService && (
                                    <div className="space-y-2">
                                        <Label>Servicio</Label>
                                        <Select name="service_id" defaultValue={rule?.service?.id?.toString()}>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Seleccionar servicio" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {services.map((s) => (
                                                    <SelectItem key={s.id} value={String(s.id)}>
                                                        {s.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {errors.service_id && <p className="text-sm text-destructive">{errors.service_id}</p>}
                                    </div>
                                )}

                                {/* Type + Value */}
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <Label>Tipo</Label>
                                        <Select name="type" defaultValue={rule?.type ?? 'percentage'}>
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="percentage">Porcentaje (%)</SelectItem>
                                                <SelectItem value="fixed">Monto fijo</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {errors.type && <p className="text-sm text-destructive">{errors.type}</p>}
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Valor</Label>
                                        <Input
                                            name="value"
                                            type="number"
                                            min="0.01"
                                            step="0.01"
                                            defaultValue={rule?.value}
                                            placeholder="0.00"
                                        />
                                        {errors.value && <p className="text-sm text-destructive">{errors.value}</p>}
                                    </div>
                                </div>

                                {/* Dates */}
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <Label>Vigencia desde</Label>
                                        <Input
                                            name="effective_from"
                                            type="date"
                                            defaultValue={rule?.effective_from ?? new Date().toISOString().split('T')[0]}
                                        />
                                        {errors.effective_from && <p className="text-sm text-destructive">{errors.effective_from}</p>}
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Vigencia hasta (opcional)</Label>
                                        <Input name="effective_until" type="date" defaultValue={rule?.effective_until ?? ''} />
                                        {errors.effective_until && <p className="text-sm text-destructive">{errors.effective_until}</p>}
                                    </div>
                                </div>
                            </div>

                            <DialogFooter>
                                <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                                    Cancelar
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Guardando...' : isEditing ? 'Guardar cambios' : 'Crear regla'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
