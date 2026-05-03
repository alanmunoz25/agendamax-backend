import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { CheckCircle } from 'lucide-react';
import { format } from 'date-fns';

export interface AppointmentForPos {
    id: number;
    scheduled_at: string;
    scheduled_until: string;
    status: 'pending' | 'confirmed' | 'in_progress' | 'completed' | 'cancelled';
    final_price: string | null;
    ticket_id: number | null;
    client: { id: number; name: string; phone?: string } | null;
    employee: { id: number; user: { name: string } } | null;
    services: Array<{ id: number; name: string; price: string; duration: number }>;
}

interface AppointmentCardProps {
    appointment: AppointmentForPos;
    onCheckout: (appointment: AppointmentForPos) => void;
}

type AppointmentStatus = AppointmentForPos['status'];

const STATUS_CONFIG: Record<AppointmentStatus, { label: string; className: string }> = {
    pending: { label: 'Programada', className: 'bg-secondary text-secondary-foreground' },
    confirmed: { label: 'Programada', className: 'bg-secondary text-secondary-foreground' },
    in_progress: {
        label: 'En curso',
        className: 'bg-[var(--color-cyan-brand)]/10 text-[var(--color-cyan-brand)]',
    },
    completed: {
        label: 'Completada',
        className: 'bg-[var(--color-amber-brand)]/10 text-[var(--color-amber-brand)]',
    },
    cancelled: { label: 'Cancelada', className: 'bg-destructive/10 text-destructive' },
};

export function AppointmentCard({ appointment, onCheckout }: AppointmentCardProps) {
    const { status, ticket_id, client, employee, services, scheduled_at } = appointment;

    const isAlreadyPaid = ticket_id !== null;
    const isCancelled = status === 'cancelled';
    const isReadyToCheckout = status === 'completed' && !isAlreadyPaid;

    const containerClass = [
        'rounded-lg border border-border bg-card p-4 flex items-start justify-between gap-3',
        isReadyToCheckout ? 'border-[var(--color-amber-brand)]/50 bg-[var(--color-amber-brand)]/5' : '',
        isAlreadyPaid ? 'opacity-60' : '',
        isCancelled ? 'opacity-50' : '',
    ]
        .filter(Boolean)
        .join(' ');

    const statusConfig = STATUS_CONFIG[status];
    const displayServices = services.slice(0, 2);
    const extraCount = services.length - 2;

    return (
        <div className={containerClass}>
            <div className="flex min-w-0 flex-1 flex-col gap-1">
                <span className="text-xs font-mono text-muted-foreground">
                    {format(new Date(scheduled_at), 'HH:mm')}
                </span>
                <span className="font-semibold text-foreground truncate">
                    {client?.name ?? 'Sin cliente'}
                </span>
                <div className="flex flex-wrap items-center gap-1">
                    {displayServices.map((s) => (
                        <span key={s.id} className="text-xs text-muted-foreground">
                            {s.name}
                        </span>
                    ))}
                    {extraCount > 0 && (
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <span className="text-xs text-muted-foreground cursor-help underline decoration-dotted">
                                    y {extraCount} más
                                </span>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>{services.slice(2).map((s) => s.name).join(', ')}</p>
                            </TooltipContent>
                        </Tooltip>
                    )}
                </div>
                {employee && (
                    <span className="text-xs text-muted-foreground">{employee.user.name}</span>
                )}
            </div>

            <div className="flex flex-col items-end gap-2 shrink-0">
                <Badge className={statusConfig.className}>{statusConfig.label}</Badge>

                {isAlreadyPaid && (
                    <Badge className="bg-[var(--color-green-brand)]/10 text-[var(--color-green-brand)] dark:bg-[var(--color-green-brand)]/20">
                        <CheckCircle className="size-3" />
                        Cobrada
                    </Badge>
                )}

                {!isAlreadyPaid && !isCancelled && isReadyToCheckout && (
                    <Button
                        size="sm"
                        className="bg-[var(--color-amber-brand)] hover:bg-[var(--color-amber-brand)]/90 text-white"
                        onClick={() => onCheckout(appointment)}
                    >
                        Cobrar ●
                    </Button>
                )}

                {!isAlreadyPaid && !isCancelled && !isReadyToCheckout && (
                    <Button size="sm" variant="outline" onClick={() => onCheckout(appointment)}>
                        Cobrar
                    </Button>
                )}
            </div>
        </div>
    );
}
