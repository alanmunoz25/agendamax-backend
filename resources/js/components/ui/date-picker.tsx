import { cn } from '@/lib/utils';
import { Input } from '@/components/ui/input';

interface DatePickerProps {
    value?: string;
    onChange?: (value: string) => void;
    placeholder?: string;
    disabled?: boolean;
    min?: string;
    max?: string;
    className?: string;
}

export function DatePicker({ value, onChange, placeholder, disabled, min, max, className }: DatePickerProps) {
    return (
        <Input
            type="date"
            value={value ?? ''}
            onChange={(e) => onChange?.(e.target.value)}
            placeholder={placeholder}
            disabled={disabled}
            min={min}
            max={max}
            className={cn('cursor-pointer', className)}
        />
    );
}
