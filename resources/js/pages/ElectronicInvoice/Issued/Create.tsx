import AppLayout from '@/layouts/app-layout';
import { EnvironmentBanner } from '@/components/electronic-invoice/environment-banner';
import { IssuedEcfWizard } from '@/components/electronic-invoice/issued-ecf-wizard';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';

interface Service {
    id: number;
    name: string;
    price: string;
}

interface Props {
    sequences: Record<string, { available: number }>;
    services: Service[];
    config: { ambiente: string };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Facturación Electrónica', href: '/admin/electronic-invoice/dashboard' },
    { title: 'e-CFs Emitidos', href: '/admin/electronic-invoice/issued' },
    { title: 'Emitir e-CF' },
];

export default function IssuedCreate({ sequences, services, config }: Props) {
    return (
        <AppLayout title="Emitir e-CF" breadcrumbs={breadcrumbs}>
            <Head title="Emitir e-CF" />
            <div className="mx-auto max-w-3xl space-y-6">
                <EnvironmentBanner ambiente={config.ambiente} />

                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-foreground">
                        Nuevo Comprobante Fiscal Electrónico
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Completa los 3 pasos para emitir y enviar el e-CF a DGII.
                    </p>
                </div>

                <div className="rounded-lg border border-border bg-card p-6">
                    <IssuedEcfWizard
                        sequences={sequences}
                        services={services}
                        ambiente={config.ambiente}
                        onSuccess={(ecfId) => {
                            if (ecfId > 0) {
                                router.visit(
                                    `/admin/electronic-invoice/issued/${ecfId}`
                                );
                            } else {
                                router.visit('/admin/electronic-invoice/issued');
                            }
                        }}
                        onCancel={() =>
                            router.visit('/admin/electronic-invoice/issued')
                        }
                    />
                </div>
            </div>
        </AppLayout>
    );
}
