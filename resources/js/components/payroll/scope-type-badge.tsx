import { Badge } from '@/components/ui/badge';

type ScopeType = 'global' | 'per_service' | 'per_employee' | 'specific';

const SCOPE_CONFIG: Record<ScopeType, { label: string; className: string }> = {
    global: { label: 'Global', className: 'bg-secondary text-secondary-foreground' },
    per_service: { label: 'Por Servicio', className: 'border border-border bg-background text-foreground' },
    per_employee: { label: 'Por Empleado', className: 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300' },
    specific: {
        label: 'Específica',
        className: 'bg-[var(--color-green-brand)]/10 text-[var(--color-green-brand)] dark:bg-[var(--color-green-brand)]/20',
    },
};

export function ScopeTypeBadge({ scope }: { scope: ScopeType }) {
    const config = SCOPE_CONFIG[scope] ?? SCOPE_CONFIG.global;

    return <Badge className={config.className}>{config.label}</Badge>;
}
