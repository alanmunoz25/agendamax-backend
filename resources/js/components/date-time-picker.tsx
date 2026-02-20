import * as React from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

interface DateTimePickerProps {
    value?: string;
    onChange?: (value: string) => void;
    placeholder?: string;
    disabled?: boolean;
    minDate?: Date;
    className?: string;
}

export function DateTimePicker({
    value,
    onChange,
    placeholder = 'Pick a date and time',
    disabled = false,
    minDate,
    className,
}: DateTimePickerProps) {
    const formatForInput = (isoString: string): string => {
        const date = new Date(isoString);
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        return `${year}-${month}-${day}T${hours}:${minutes}`;
    };

    const formatForDisplay = (isoString: string): string => {
        const date = new Date(isoString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const localDatetime = e.target.value;
        if (!localDatetime) {
            onChange?.('');
            return;
        }

        // Convert to ISO string
        const date = new Date(localDatetime);
        onChange?.(date.toISOString());
    };

    const minDatetime = minDate
        ? formatForInput(minDate.toISOString())
        : formatForInput(new Date().toISOString());

    return (
        <div className={cn('space-y-2', className)}>
            <Input
                type="datetime-local"
                value={value ? formatForInput(value) : ''}
                onChange={handleChange}
                disabled={disabled}
                min={minDatetime}
                className="w-full"
            />
            {value && (
                <p className="text-xs text-muted-foreground">
                    {formatForDisplay(value)}
                </p>
            )}
        </div>
    );
}
