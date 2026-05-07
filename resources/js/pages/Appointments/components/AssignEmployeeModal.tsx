import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from 'react-i18next';
import { update } from '@/actions/App/Http/Controllers/AppointmentServiceController';

export interface AppointmentServiceForAssign {
    id: number;
    service: {
        id: number;
        name: string;
    };
    employee: {
        id: number;
        name: string;
    } | null;
}

interface EmployeeOption {
    id: number;
    name: string;
    services: { id: number }[];
}

interface AssignEmployeeModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    appointmentId: number;
    appointmentService: AppointmentServiceForAssign | null;
    employees: EmployeeOption[];
}

export function AssignEmployeeModal({
    open,
    onOpenChange,
    appointmentId,
    appointmentService,
    employees,
}: AssignEmployeeModalProps) {
    const { t } = useTranslation();
    const [selectedEmployeeId, setSelectedEmployeeId] = useState<string>('');
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    // Only show employees that offer this specific service
    const eligibleEmployees = appointmentService
        ? employees.filter((emp) =>
              emp.services.some((s) => s.id === appointmentService.service.id),
          )
        : [];

    const handleClose = () => {
        setSelectedEmployeeId('');
        setErrors({});
        onOpenChange(false);
    };

    const handleSubmit = () => {
        if (!selectedEmployeeId) {
            setErrors({ employee_id: t('common.required') });
            return;
        }

        if (!appointmentService) {
            return;
        }

        setProcessing(true);
        setErrors({});

        router.patch(
            update.url({ appointment: appointmentId, appointmentService: appointmentService.id }),
            { employee_id: parseInt(selectedEmployeeId) },
            {
                preserveScroll: true,
                onSuccess: () => {
                    handleClose();
                    router.reload({ only: ['appointment_service_lines'] });
                },
                onError: (serverErrors) => {
                    setErrors(serverErrors as Record<string, string>);
                    setProcessing(false);
                },
                onFinish: () => {
                    setProcessing(false);
                },
            },
        );
    };

    const isAssigning = appointmentService?.employee === null;

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {isAssigning
                            ? t('appointments.service.assign_employee')
                            : t('appointments.service.change_employee')}
                    </DialogTitle>
                </DialogHeader>

                <div className="space-y-4 py-2">
                    {appointmentService && (
                        <p className="text-sm text-muted-foreground">
                            {appointmentService.service.name}
                        </p>
                    )}

                    <div className="space-y-2">
                        <Label>{t('appointments.employee_label')} *</Label>
                        <Select
                            value={selectedEmployeeId}
                            onValueChange={(value) => {
                                setSelectedEmployeeId(value);
                                setErrors({});
                            }}
                            disabled={eligibleEmployees.length === 0}
                        >
                            <SelectTrigger>
                                <SelectValue
                                    placeholder={
                                        eligibleEmployees.length === 0
                                            ? t('appointments.add_service.no_employees')
                                            : t('appointments.employee_placeholder')
                                    }
                                />
                            </SelectTrigger>
                            <SelectContent>
                                {eligibleEmployees.map((emp) => (
                                    <SelectItem key={emp.id} value={emp.id.toString()}>
                                        {emp.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.employee_id && (
                            <p className="text-sm text-destructive">{errors.employee_id}</p>
                        )}
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={handleClose}
                        disabled={processing}
                    >
                        {t('common.cancel')}
                    </Button>
                    <Button
                        type="button"
                        onClick={handleSubmit}
                        disabled={processing || !selectedEmployeeId || eligibleEmployees.length === 0}
                    >
                        {processing ? t('common.processing') : t('common.confirm')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
