import { Badge } from '@/components/ui/badge';
import type { AppointmentStatus } from '@/types/models';
import { type VariantProps } from 'class-variance-authority';

interface StatusBadgeProps {
    status: AppointmentStatus;
    className?: string;
}

const statusConfig: Record<
    AppointmentStatus,
    {
        label: string;
        variant: VariantProps<typeof Badge>['variant'];
    }
> = {
    pending: {
        label: 'Pending',
        variant: 'secondary',
    },
    confirmed: {
        label: 'Confirmed',
        variant: 'default',
    },
    completed: {
        label: 'Completed',
        variant: 'outline',
    },
    cancelled: {
        label: 'Cancelled',
        variant: 'destructive',
    },
};

export function StatusBadge({ status, className }: StatusBadgeProps) {
    const config = statusConfig[status];

    return (
        <Badge variant={config.variant} className={className}>
            {config.label}
        </Badge>
    );
}
