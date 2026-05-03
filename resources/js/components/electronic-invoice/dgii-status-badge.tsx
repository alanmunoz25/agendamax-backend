import { Badge } from '@/components/ui/badge';
import { CheckCircle, XCircle, Clock, AlertCircle, Loader } from 'lucide-react';

type DgiiStatus =
    | 'draft'
    | 'signed'
    | 'sent'
    | 'accepted'
    | 'conditional_accepted'
    | 'rejected'
    | 'error'
    | 'contingency'
    | 'pending'
    | string;

interface DgiiStatusBadgeProps {
    status: DgiiStatus;
    className?: string;
}

const statusConfig: Record<
    string,
    { label: string; icon: React.ElementType; className: string }
> = {
    draft: {
        label: 'Borrador',
        icon: Clock,
        className: 'bg-muted text-muted-foreground',
    },
    signed: {
        label: 'Firmado',
        icon: Clock,
        className: 'bg-blue-100 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300',
    },
    sent: {
        label: 'En proceso',
        icon: Loader,
        className: 'bg-blue-100 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300',
    },
    accepted: {
        label: 'Aceptado',
        icon: CheckCircle,
        className:
            'bg-[var(--color-green-brand)]/10 text-[var(--color-green-brand)] border-[var(--color-green-brand)]/30',
    },
    conditional_accepted: {
        label: 'Aceptado Cond.',
        icon: AlertCircle,
        className:
            'bg-[var(--color-amber-brand)]/10 text-[var(--color-amber-brand)] border-[var(--color-amber-brand)]/30',
    },
    rejected: {
        label: 'Rechazado',
        icon: XCircle,
        className: 'bg-destructive/10 text-destructive border-destructive/30',
    },
    error: {
        label: 'Error',
        icon: XCircle,
        className: 'bg-destructive/10 text-destructive border-destructive/30',
    },
    contingency: {
        label: 'Contingencia',
        icon: AlertCircle,
        className:
            'bg-[var(--color-amber-brand)]/10 text-[var(--color-amber-brand)] border-[var(--color-amber-brand)]/30',
    },
    pending: {
        label: 'Pendiente',
        icon: Clock,
        className:
            'bg-[var(--color-amber-brand)]/10 text-[var(--color-amber-brand)] border-[var(--color-amber-brand)]/30',
    },
};

export function DgiiStatusBadge({ status, className }: DgiiStatusBadgeProps) {
    const config = statusConfig[status] ?? {
        label: status,
        icon: Clock,
        className: 'bg-muted text-muted-foreground',
    };

    const Icon = config.icon;
    const isProcessing = status === 'sent';

    return (
        <Badge
            variant="outline"
            className={`inline-flex items-center gap-1.5 ${config.className} ${className ?? ''}`}
        >
            <Icon
                className={`h-3.5 w-3.5 ${isProcessing ? 'animate-spin' : ''}`}
            />
            {config.label}
        </Badge>
    );
}
