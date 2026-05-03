import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Form } from '@inertiajs/react';

interface EditBaseSalaryModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    employee: { id: number; name: string | null | undefined; base_salary: string | null };
    updateUrl: string;
}

export function EditBaseSalaryModal({ open, onOpenChange, employee, updateUrl }: EditBaseSalaryModalProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Editar Salario Base</DialogTitle>
                    <DialogDescription>
                        Actualiza el salario base de <strong>{employee.name}</strong>. Este cambio no afecta el historial de
                        nómina ya generado.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    action={updateUrl}
                    method="patch"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="space-y-4 py-4">
                                <div className="space-y-2">
                                    <Label htmlFor="base_salary">Salario Base</Label>
                                    <Input
                                        id="base_salary"
                                        name="base_salary"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        defaultValue={employee.base_salary ?? '0'}
                                        placeholder="0.00"
                                    />
                                    {errors.base_salary && <p className="text-sm text-destructive">{errors.base_salary}</p>}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    El salario base se usará como snapshot en los próximos períodos generados.
                                </p>
                            </div>

                            <DialogFooter>
                                <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                                    Cancelar
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Guardando...' : 'Guardar'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
