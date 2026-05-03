import { AlertTriangle } from 'lucide-react';

export function NegativeGrossBadge({ amount }: { amount: string }) {
    if (!amount.startsWith('-')) {
        return <span className="font-medium text-foreground">${amount}</span>;
    }

    const abs = amount.slice(1);
    return (
        <span className="inline-flex items-center gap-1 rounded-md bg-red-100 px-2 py-0.5 text-sm font-bold text-red-700 dark:bg-red-900/30 dark:text-red-400">
            <AlertTriangle className="h-3.5 w-3.5" />
            Adeudo: -${abs}
        </span>
    );
}
