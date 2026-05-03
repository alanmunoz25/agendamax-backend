import { Badge } from '@/components/ui/badge';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { WifiOff } from 'lucide-react';

type EcfStatus = 'pending' | 'emitted' | 'error' | 'na' | 'offline_pending';

interface EcfStatusBadgeProps {
    status: EcfStatus;
    errorMessage?: string;
}

export function EcfStatusBadge({ status, errorMessage }: EcfStatusBadgeProps) {
    if (status === 'pending') {
        return (
            <Badge className="bg-[var(--color-amber-brand)]/10 text-[var(--color-amber-brand)]">
                Pendiente
            </Badge>
        );
    }

    if (status === 'emitted') {
        return (
            <Badge className="bg-[var(--color-green-brand)]/10 text-[var(--color-green-brand)] dark:bg-[var(--color-green-brand)]/20">
                Emitida
            </Badge>
        );
    }

    if (status === 'error') {
        return (
            <Tooltip>
                <TooltipTrigger asChild>
                    <Badge className="bg-destructive/10 text-destructive cursor-help">
                        Error DGII
                    </Badge>
                </TooltipTrigger>
                {errorMessage && (
                    <TooltipContent>
                        <p>{errorMessage}</p>
                    </TooltipContent>
                )}
            </Tooltip>
        );
    }

    if (status === 'offline_pending') {
        return (
            <Badge className="bg-[var(--color-amber-brand)]/10 text-[var(--color-amber-brand)]">
                <WifiOff className="size-3" />
                Offline
            </Badge>
        );
    }

    return <Badge className="bg-secondary text-secondary-foreground">N/A</Badge>;
}
