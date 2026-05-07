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
import { store } from '@/actions/App/Http/Controllers/AppointmentServiceController';

interface ServiceOption {
    id: number;
    name: string;
    price: number | string;
    duration: number;
}

export interface EmployeeWithServices {
    id: number;
    name: string;
    photo_url: string | null;
    services: ServiceOption[];
}

interface AddAppointmentServiceModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    appointmentId: number;
    existingServiceIds: number[];
    employees: EmployeeWithServices[];
}

export function AddAppointmentServiceModal({
    open,
    onOpenChange,
    appointmentId,
    existingServiceIds,
    employees,
}: AddAppointmentServiceModalProps) {
    const { t } = useTranslation();
    const [selectedEmployeeId, setSelectedEmployeeId] = useState<string>('');
    const [selectedServiceId, setSelectedServiceId] = useState<string>('');
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const selectedEmployee = employees.find(
        (e) => e.id.toString() === selectedEmployeeId
    );

    const availableServices = (selectedEmployee?.services ?? []).filter(
        (s) => !existingServiceIds.includes(s.id)
    );

    const handleEmployeeChange = (value: string) => {
        setSelectedEmployeeId(value);
        setSelectedServiceId('');
        setErrors({});
    };

    const handleClose = () => {
        setSelectedEmployeeId('');
        setSelectedServiceId('');
        setErrors({});
        onOpenChange(false);
    };

    const handleSubmit = () => {
        const newErrors: Record<string, string> = {};

        if (!selectedEmployeeId) {
            newErrors.employee_id = t('common.required');
        }
        if (!selectedServiceId) {
            newErrors.service_id = t('common.required');
        }

        if (Object.keys(newErrors).length > 0) {
            setErrors(newErrors);
            return;
        }

        setProcessing(true);
        setErrors({});

        router.post(
            store.url(appointmentId),
            {
                employee_id: parseInt(selectedEmployeeId),
                service_id: parseInt(selectedServiceId),
            },
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
            }
        );
    };

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{t('appointments.add_service.title')}</DialogTitle>
                </DialogHeader>

                <div className="space-y-4 py-2">
                    {/* Employee dropdown */}
                    <div className="space-y-2">
                        <Label>{t('appointments.add_service.employee_label')} *</Label>
                        <Select
                            value={selectedEmployeeId}
                            onValueChange={handleEmployeeChange}
                            disabled={employees.length === 0}
                        >
                            <SelectTrigger>
                                <SelectValue
                                    placeholder={
                                        employees.length === 0
                                            ? t('appointments.add_service.no_employees')
                                            : t('appointments.add_service.employee_placeholder')
                                    }
                                />
                            </SelectTrigger>
                            <SelectContent>
                                {employees.map((emp) => (
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

                    {/* Service dropdown — disabled until employee selected */}
                    <div className="space-y-2">
                        <Label>{t('appointments.add_service.service_label')} *</Label>
                        <Select
                            value={selectedServiceId}
                            onValueChange={setSelectedServiceId}
                            disabled={!selectedEmployeeId}
                        >
                            <SelectTrigger>
                                <SelectValue
                                    placeholder={
                                        !selectedEmployeeId
                                            ? t('appointments.add_service.service_placeholder')
                                            : availableServices.length === 0
                                              ? t('appointments.add_service.all_services_added')
                                              : t('appointments.service_placeholder')
                                    }
                                />
                            </SelectTrigger>
                            <SelectContent>
                                {availableServices.length === 0 ? (
                                    <SelectItem value="__empty__" disabled>
                                        {selectedEmployee
                                            ? t('appointments.add_service.all_services_added')
                                            : t('appointments.add_service.no_services')}
                                    </SelectItem>
                                ) : (
                                    availableServices.map((svc) => (
                                        <SelectItem key={svc.id} value={svc.id.toString()}>
                                            <div className="flex flex-col">
                                                <span>{svc.name}</span>
                                                <span className="text-xs text-muted-foreground">
                                                    RD${svc.price} · {svc.duration} min
                                                </span>
                                            </div>
                                        </SelectItem>
                                    ))
                                )}
                            </SelectContent>
                        </Select>
                        {errors.service_id && (
                            <p className="text-sm text-destructive">{errors.service_id}</p>
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
                        disabled={processing || !selectedEmployeeId || !selectedServiceId}
                    >
                        {processing
                            ? t('appointments.add_service.submitting_btn')
                            : t('appointments.add_service.submit_btn')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
