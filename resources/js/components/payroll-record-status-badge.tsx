import { Badge } from '@/components/ui/badge';

type RecordStatus = 'draft' | 'approved' | 'paid' | 'voided';

const config: Record<RecordStatus, { label: string; variant: 'secondary' | 'default' | 'destructive' | 'outline'; className?: string }> = {
    draft: {
        label: 'Borrador',
        variant: 'secondary',
    },
    approved: {
        label: 'Aprobado',
        variant: 'default',
        className: 'bg-[var(--color-amber-brand)] text-black hover:bg-[var(--color-amber-brand)]',
    },
    paid: {
        label: 'Pagado',
        variant: 'default',
        className: 'bg-[var(--color-green-brand)] text-black hover:bg-[var(--color-green-brand)]',
    },
    voided: {
        label: 'Anulado',
        variant: 'destructive',
    },
};

export function PayrollRecordStatusBadge({ status }: { status: RecordStatus }) {
    const { label, variant, className } = config[status] ?? config.draft;
    return (
        <Badge variant={variant} className={className}>
            {label}
        </Badge>
    );
}
