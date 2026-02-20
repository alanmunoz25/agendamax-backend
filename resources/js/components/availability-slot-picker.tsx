import * as React from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { Trash2, Plus } from 'lucide-react';

export interface ScheduleSlot {
    day_of_week: number;
    start_time: string;
    end_time: string;
    is_available: boolean;
}

interface AvailabilitySlotPickerProps {
    value: ScheduleSlot[];
    onChange: (slots: ScheduleSlot[]) => void;
    disabled?: boolean;
    className?: string;
}

const DAYS = [
    { value: 0, label: 'Sunday' },
    { value: 1, label: 'Monday' },
    { value: 2, label: 'Tuesday' },
    { value: 3, label: 'Wednesday' },
    { value: 4, label: 'Thursday' },
    { value: 5, label: 'Friday' },
    { value: 6, label: 'Saturday' },
];

export function AvailabilitySlotPicker({
    value = [],
    onChange,
    disabled = false,
    className,
}: AvailabilitySlotPickerProps) {
    const addSlot = (dayOfWeek: number) => {
        const newSlot: ScheduleSlot = {
            day_of_week: dayOfWeek,
            start_time: '09:00',
            end_time: '17:00',
            is_available: true,
        };
        onChange([...value, newSlot]);
    };

    const removeSlot = (index: number) => {
        const newSlots = value.filter((_, i) => i !== index);
        onChange(newSlots);
    };

    const updateSlot = (index: number, field: keyof ScheduleSlot, newValue: string | boolean | number) => {
        const newSlots = value.map((slot, i) => {
            if (i === index) {
                return { ...slot, [field]: newValue };
            }
            return slot;
        });
        onChange(newSlots);
    };

    const hasDaySchedule = (dayOfWeek: number) => {
        return value.some((slot) => slot.day_of_week === dayOfWeek);
    };

    const getDaySchedule = (dayOfWeek: number) => {
        return value.find((slot) => slot.day_of_week === dayOfWeek);
    };

    const validateTimeRange = (startTime: string, endTime: string): boolean => {
        if (!startTime || !endTime) return true;
        const start = new Date(`2000-01-01T${startTime}`);
        const end = new Date(`2000-01-01T${endTime}`);
        return end > start;
    };

    return (
        <div className={cn('space-y-4', className)}>
            <div className="space-y-2">
                <Label>Weekly Availability Schedule</Label>
                <p className="text-sm text-muted-foreground">
                    Set the employee's available hours for each day of the week. Only one schedule per day is allowed.
                </p>
            </div>

            <div className="space-y-3">
                {DAYS.map((day) => {
                    const daySchedule = getDaySchedule(day.value);
                    const hasSchedule = hasDaySchedule(day.value);
                    const slotIndex = value.findIndex((s) => s.day_of_week === day.value);

                    return (
                        <Card key={day.value} className="p-4">
                            <div className="flex items-center justify-between gap-4">
                                <div className="min-w-[100px]">
                                    <Label className="text-base font-medium">{day.label}</Label>
                                </div>

                                {!hasSchedule ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => addSlot(day.value)}
                                        disabled={disabled}
                                        className="ml-auto"
                                    >
                                        <Plus className="h-4 w-4 mr-2" />
                                        Add Hours
                                    </Button>
                                ) : (
                                    <div className="flex items-center gap-3 flex-1">
                                        <div className="flex items-center gap-2 flex-1">
                                            <div className="flex-1">
                                                <Label className="text-xs text-muted-foreground mb-1">
                                                    Start Time
                                                </Label>
                                                <Input
                                                    type="time"
                                                    value={daySchedule?.start_time || ''}
                                                    onChange={(e) =>
                                                        updateSlot(slotIndex, 'start_time', e.target.value)
                                                    }
                                                    disabled={disabled}
                                                    className="w-full"
                                                />
                                            </div>
                                            <div className="flex-1">
                                                <Label className="text-xs text-muted-foreground mb-1">
                                                    End Time
                                                </Label>
                                                <Input
                                                    type="time"
                                                    value={daySchedule?.end_time || ''}
                                                    onChange={(e) =>
                                                        updateSlot(slotIndex, 'end_time', e.target.value)
                                                    }
                                                    disabled={disabled}
                                                    className={cn(
                                                        'w-full',
                                                        daySchedule &&
                                                            !validateTimeRange(
                                                                daySchedule.start_time,
                                                                daySchedule.end_time
                                                            ) &&
                                                            'border-red-500'
                                                    )}
                                                />
                                                {daySchedule &&
                                                    !validateTimeRange(
                                                        daySchedule.start_time,
                                                        daySchedule.end_time
                                                    ) && (
                                                        <p className="text-xs text-red-500 mt-1">
                                                            End time must be after start time
                                                        </p>
                                                    )}
                                            </div>
                                        </div>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => removeSlot(slotIndex)}
                                            disabled={disabled}
                                            className="text-destructive hover:text-destructive"
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                )}
                            </div>
                        </Card>
                    );
                })}
            </div>

            {value.length === 0 && (
                <div className="text-center py-8 text-muted-foreground">
                    <p className="text-sm">No availability set. Add hours for each working day.</p>
                </div>
            )}
        </div>
    );
}
