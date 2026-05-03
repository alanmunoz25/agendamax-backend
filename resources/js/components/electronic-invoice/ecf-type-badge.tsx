import { Badge } from '@/components/ui/badge';

interface EcfTypeBadgeProps {
    tipo: number | string;
    className?: string;
}

const typeLabels: Record<string, string> = {
    '31': 'Crédito Fiscal',
    '32': 'Consumidor Final',
    '33': 'Nota de Débito',
    '34': 'Nota de Crédito',
    '41': 'Compra',
    '43': 'Gastos Menores',
    '44': 'Reg. Especiales',
    '45': 'Gubernamental',
    '46': 'Exportaciones',
    '47': 'Pagos Exterior',
};

export function EcfTypeBadge({ tipo, className }: EcfTypeBadgeProps) {
    const key = String(tipo);
    const label = typeLabels[key] ?? `Tipo ${key}`;

    return (
        <Badge variant="secondary" className={className}>
            {key} — {label}
        </Badge>
    );
}
