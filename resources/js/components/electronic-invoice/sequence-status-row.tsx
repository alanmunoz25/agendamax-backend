import { AlertTriangle, CheckCircle } from 'lucide-react';

interface SequenceStatusRowProps {
    tipo: string;
    available: number;
    hasta: number;
}

export function SequenceStatusRow({ tipo, available, hasta }: SequenceStatusRowProps) {
    const isLow = available <= 50;
    const isExhausted = available === 0;

    const typeLabels: Record<string, string> = {
        '31': 'Tipo 31 — Crédito Fiscal',
        '32': 'Tipo 32 — Consumidor Final',
        '33': 'Tipo 33 — Nota de Débito',
        '34': 'Tipo 34 — Nota de Crédito',
    };

    const label = typeLabels[tipo] ?? `Tipo ${tipo}`;
    const pct = hasta > 0 ? Math.round((available / hasta) * 100) : 0;

    return (
        <div className="flex items-center justify-between rounded-md border border-border bg-card px-4 py-3">
            <div className="flex items-center gap-3">
                {isExhausted || isLow ? (
                    <AlertTriangle
                        className={`h-4 w-4 ${isExhausted ? 'text-destructive' : 'text-[var(--color-amber-brand)]'}`}
                    />
                ) : (
                    <CheckCircle className="h-4 w-4 text-[var(--color-green-brand)]" />
                )}
                <span className="text-sm font-medium text-foreground">{label}</span>
            </div>
            <div className="flex items-center gap-2">
                <div
                    className="h-2 w-24 overflow-hidden rounded-full bg-muted"
                    title={`${available} de ${hasta} disponibles`}
                >
                    <div
                        className={`h-full rounded-full transition-all ${
                            isExhausted
                                ? 'bg-destructive'
                                : isLow
                                  ? 'bg-[var(--color-amber-brand)]'
                                  : 'bg-[var(--color-green-brand)]'
                        }`}
                        style={{ width: `${pct}%` }}
                    />
                </div>
                <span
                    className={`text-sm font-medium ${
                        isExhausted
                            ? 'text-destructive'
                            : isLow
                              ? 'text-[var(--color-amber-brand)]'
                              : 'text-[var(--color-green-brand)]'
                    }`}
                >
                    {available.toLocaleString()} disponibles
                </span>
            </div>
        </div>
    );
}
