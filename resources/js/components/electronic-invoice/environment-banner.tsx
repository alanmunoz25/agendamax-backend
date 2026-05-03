import { AlertTriangle } from 'lucide-react';
import { Link } from '@inertiajs/react';

interface EnvironmentBannerProps {
    ambiente: string;
}

export function EnvironmentBanner({ ambiente }: EnvironmentBannerProps) {
    if (ambiente === 'ECF') {
        return null;
    }

    const isTest = ambiente === 'TestECF';

    return (
        <div
            className={`flex items-center justify-between rounded-lg border px-4 py-3 text-sm ${
                isTest
                    ? 'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-300'
                    : 'border-orange-300 bg-orange-50 text-orange-800 dark:border-orange-700 dark:bg-orange-950/30 dark:text-orange-300'
            }`}
        >
            <div className="flex items-center gap-2">
                <AlertTriangle className="h-4 w-4 shrink-0" />
                {isTest ? (
                    <span>
                        <strong>AMBIENTE DE PRUEBAS (TestECF)</strong> — Los
                        e-CFs emitidos NO tienen validez fiscal. Cambia a
                        producción en Configuración FE cuando estés listo.
                    </span>
                ) : (
                    <span>
                        <strong>CERTIFICACIÓN (CertECF)</strong> — Este ambiente
                        es para certificación con DGII.
                    </span>
                )}
            </div>
            <Link
                href="/admin/electronic-invoice/settings"
                className="ml-4 shrink-0 font-medium underline underline-offset-2 hover:no-underline"
            >
                Ir a Configuración →
            </Link>
        </div>
    );
}
