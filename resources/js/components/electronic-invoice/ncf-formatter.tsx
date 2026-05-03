interface NcfFormatterProps {
    ncf: string;
    className?: string;
}

/**
 * Formats a DGII eNCF string for display.
 * Example: E310000000042 → E31·0000000042
 */
export function NcfFormatter({ ncf, className }: NcfFormatterProps) {
    const formatted = ncf.length >= 3 ? `${ncf.slice(0, 3)}·${ncf.slice(3)}` : ncf;

    return (
        <span className={`font-mono tracking-tight ${className ?? ''}`}>
            {formatted}
        </span>
    );
}
