import { Badge } from '@/components/ui/badge';

type PeriodStatus = 'open' | 'closed';

const config: Record<PeriodStatus, { label: string; className: string }> = {
    open: {
        label: 'Abierto',
        className: 'bg-[var(--color-blue-brand)] text-white hover:bg-[var(--color-blue-brand)]',
    },
    closed: {
        label: 'Cerrado',
        className: 'border-[var(--color-gray-brand)] text-[var(--color-gray-brand)]',
    },
};

export function PayrollPeriodStatusBadge({ status }: { status: PeriodStatus }) {
    const { label, className } = config[status] ?? config.closed;
    return (
        <Badge variant={status === 'closed' ? 'outline' : 'default'} className={className}>
            {label}
        </Badge>
    );
}
