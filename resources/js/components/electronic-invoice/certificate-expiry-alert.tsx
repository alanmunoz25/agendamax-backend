import { AlertTriangle, XCircle } from 'lucide-react';
import { Link } from '@inertiajs/react';

interface CertificateExpiryAlertProps {
    expiresAt: string | null;
    daysRemaining?: number;
}

export function CertificateExpiryAlert({
    expiresAt,
    daysRemaining,
}: CertificateExpiryAlertProps) {
    if (!expiresAt) {
        return null;
    }

    const isExpired = daysRemaining !== undefined && daysRemaining <= 0;

    if (!isExpired && (daysRemaining === undefined || daysRemaining > 30)) {
        return null;
    }

    return (
        <div
            className={`flex items-center justify-between rounded-lg border px-4 py-3 text-sm ${
                isExpired
                    ? 'border-destructive/30 bg-destructive/5 text-destructive'
                    : 'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-300'
            }`}
        >
            <div className="flex items-center gap-2">
                {isExpired ? (
                    <XCircle className="h-4 w-4 shrink-0" />
                ) : (
                    <AlertTriangle className="h-4 w-4 shrink-0" />
                )}
                {isExpired ? (
                    <span>
                        <strong>CERTIFICADO DIGITAL VENCIDO</strong> — No puedes
                        emitir e-CFs hasta renovarlo. Vencido el:{' '}
                        {new Date(expiresAt).toLocaleDateString('es-DO')}
                    </span>
                ) : (
                    <span>
                        <strong>Certificado digital vence en {daysRemaining} días</strong> —{' '}
                        {expiresAt}
                    </span>
                )}
            </div>
            <Link
                href="/admin/electronic-invoice/settings"
                className="ml-4 shrink-0 font-medium underline underline-offset-2 hover:no-underline"
            >
                Renovar →
            </Link>
        </div>
    );
}
