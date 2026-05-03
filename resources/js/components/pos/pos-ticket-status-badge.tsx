import { Badge } from '@/components/ui/badge';

type TicketStatus = 'open' | 'paid' | 'voided';

const STATUS_CONFIG: Record<TicketStatus, { label: string; className: string }> = {
    open: { label: 'Abierto', className: 'bg-secondary text-secondary-foreground' },
    paid: {
        label: 'Cobrado',
        className:
            'bg-[var(--color-green-brand)]/10 text-[var(--color-green-brand)] dark:bg-[var(--color-green-brand)]/20',
    },
    voided: { label: 'Anulado', className: 'bg-destructive/10 text-destructive' },
};

export function PosTicketStatusBadge({ status }: { status: TicketStatus }) {
    const config = STATUS_CONFIG[status] ?? STATUS_CONFIG.open;

    return <Badge className={config.className}>{config.label}</Badge>;
}
