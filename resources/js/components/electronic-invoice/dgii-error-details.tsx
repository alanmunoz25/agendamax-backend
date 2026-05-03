import { useState } from 'react';
import { ChevronDown, ChevronUp, AlertCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';

interface DgiiErrorDetailsProps {
    errorMessage: string | null;
    onResend?: () => void;
}

export function DgiiErrorDetails({ errorMessage, onResend }: DgiiErrorDetailsProps) {
    const [expanded, setExpanded] = useState(false);

    if (!errorMessage) {
        return null;
    }

    return (
        <div className="rounded-lg border border-destructive/30 bg-destructive/5 p-4">
            <div className="flex items-start gap-3">
                <AlertCircle className="mt-0.5 h-5 w-5 shrink-0 text-destructive" />
                <div className="flex-1 space-y-2">
                    <p className="font-medium text-destructive">Rechazado por DGII</p>
                    <button
                        type="button"
                        onClick={() => setExpanded(!expanded)}
                        className="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                    >
                        {expanded ? (
                            <ChevronUp className="h-4 w-4" />
                        ) : (
                            <ChevronDown className="h-4 w-4" />
                        )}
                        {expanded ? 'Ocultar detalle técnico' : 'Ver detalle técnico'}
                    </button>

                    {expanded && (
                        <pre className="mt-2 max-h-40 overflow-auto rounded bg-muted p-3 text-xs text-muted-foreground">
                            {errorMessage}
                        </pre>
                    )}

                    {onResend && (
                        <div className="pt-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={onResend}
                            >
                                Reenviar sin cambios
                            </Button>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
