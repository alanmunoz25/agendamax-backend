import AppLayout from '@/layouts/app-layout';
import { SequenceStatusRow } from '@/components/electronic-invoice/sequence-status-row';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { CheckCircle, AlertTriangle } from 'lucide-react';

interface FeConfig {
    id: number;
    rnc_emisor: string | null;
    razon_social: string | null;
    nombre_comercial: string | null;
    direccion: string | null;
    municipio: string | null;
    provincia: string | null;
    telefono: string | null;
    email: string | null;
    actividad_economica: string | null;
    ambiente: string;
    activo: boolean;
    certificado_convertido: boolean;
    fecha_vigencia_cert: string | null;
    has_certificate: boolean;
}

interface Sequence {
    id: number;
    tipo: string;
    prefijo: string;
    desde: number;
    hasta: number;
    proximo_secuencial: number;
    fecha_vencimiento: string | null;
    status: string;
    available: number;
}

interface Props {
    feConfig: FeConfig | null;
    sequences: Sequence[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Facturación Electrónica', href: '/admin/electronic-invoice/dashboard' },
    { title: 'Configuración FE' },
];

export default function ElectronicInvoiceSettings({ feConfig, sequences }: Props) {
    const form = useForm({
        rnc_emisor: feConfig?.rnc_emisor ?? '',
        razon_social: feConfig?.razon_social ?? '',
        nombre_comercial: feConfig?.nombre_comercial ?? '',
        direccion: feConfig?.direccion ?? '',
        municipio: feConfig?.municipio ?? '',
        provincia: feConfig?.provincia ?? '',
        telefono: feConfig?.telefono ?? '',
        email: feConfig?.email ?? '',
        actividad_economica: feConfig?.actividad_economica ?? '',
        ambiente: feConfig?.ambiente ?? 'TestECF',
        activo: feConfig?.activo ?? false,
    });

    const certForm = useForm<{ certificate: File | null; password: string }>({
        certificate: null,
        password: '',
    });

    const handleSave = () => {
        form.put('/admin/electronic-invoice/settings');
    };

    const handleCertUpload = () => {
        certForm.post('/admin/electronic-invoice/settings/upload-certificate');
    };

    const handleTestConnectivity = () => {
        form.post('/admin/electronic-invoice/settings/test-connectivity');
    };

    return (
        <AppLayout title="Configuración FE" breadcrumbs={breadcrumbs}>
            <Head title="Configuración de Facturación Electrónica" />
            <div className="mx-auto max-w-3xl space-y-8">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-foreground">
                        Configuración de Facturación Electrónica
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Datos de empresa, certificado digital y secuencias DGII
                    </p>
                </div>

                {/* Business data */}
                <Card>
                    <CardHeader>
                        <CardTitle>Datos de la Empresa</CardTitle>
                        <CardDescription>
                            Información del emisor según registro DGII
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label>RNC Emisor *</Label>
                                <Input
                                    value={form.data.rnc_emisor}
                                    onChange={(e) =>
                                        form.setData(
                                            'rnc_emisor',
                                            e.target.value
                                        )
                                    }
                                    placeholder="Ej. 131234567"
                                />
                                {form.errors.rnc_emisor && (
                                    <p className="text-xs text-destructive">
                                        {form.errors.rnc_emisor}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <Label>Razón Social *</Label>
                                <Input
                                    value={form.data.razon_social}
                                    onChange={(e) =>
                                        form.setData(
                                            'razon_social',
                                            e.target.value
                                        )
                                    }
                                    placeholder="Nombre legal de la empresa"
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Nombre Comercial</Label>
                                <Input
                                    value={form.data.nombre_comercial}
                                    onChange={(e) =>
                                        form.setData(
                                            'nombre_comercial',
                                            e.target.value
                                        )
                                    }
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Email</Label>
                                <Input
                                    type="email"
                                    value={form.data.email}
                                    onChange={(e) =>
                                        form.setData('email', e.target.value)
                                    }
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Teléfono</Label>
                                <Input
                                    value={form.data.telefono}
                                    onChange={(e) =>
                                        form.setData(
                                            'telefono',
                                            e.target.value
                                        )
                                    }
                                />
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label>Dirección</Label>
                            <Input
                                value={form.data.direccion}
                                onChange={(e) =>
                                    form.setData('direccion', e.target.value)
                                }
                            />
                        </div>

                        <Separator />

                        <div className="space-y-2">
                            <Label>Ambiente *</Label>
                            <Select
                                value={form.data.ambiente}
                                onValueChange={(v) =>
                                    form.setData('ambiente', v)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="TestECF">
                                        TestECF (Pruebas)
                                    </SelectItem>
                                    <SelectItem value="CertECF">
                                        CertECF (Certificación)
                                    </SelectItem>
                                    <SelectItem value="ECF">
                                        ECF (Producción)
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="flex items-center gap-3 pt-2">
                            <Button
                                onClick={handleSave}
                                disabled={form.processing}
                            >
                                {form.processing ? 'Guardando...' : 'Guardar Configuración'}
                            </Button>
                            <Button
                                variant="outline"
                                onClick={handleTestConnectivity}
                                disabled={form.processing}
                            >
                                Probar Conectividad DGII
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                {/* Certificate */}
                <Card>
                    <CardHeader>
                        <CardTitle>Certificado Digital</CardTitle>
                        <CardDescription>
                            Certificado P12 emitido por la DGII para firma de
                            e-CFs
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {feConfig?.has_certificate ? (
                            <div className="flex items-center gap-2 text-sm text-[var(--color-green-brand)]">
                                <CheckCircle className="h-4 w-4" />
                                Certificado cargado y convertido correctamente
                                {feConfig.fecha_vigencia_cert && (
                                    <span className="text-muted-foreground">
                                        · Vence:{' '}
                                        {new Date(
                                            feConfig.fecha_vigencia_cert
                                        ).toLocaleDateString('es-DO')}
                                    </span>
                                )}
                            </div>
                        ) : (
                            <div className="flex items-center gap-2 text-sm text-amber-600">
                                <AlertTriangle className="h-4 w-4" />
                                Sin certificado digital cargado
                            </div>
                        )}

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label>Archivo certificado (.p12 / .pfx)</Label>
                                <Input
                                    type="file"
                                    accept=".p12,.pfx"
                                    onChange={(e) =>
                                        certForm.setData(
                                            'certificate',
                                            e.target.files?.[0] ?? null
                                        )
                                    }
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Contraseña del certificado</Label>
                                <Input
                                    type="password"
                                    value={certForm.data.password}
                                    onChange={(e) =>
                                        certForm.setData(
                                            'password',
                                            e.target.value
                                        )
                                    }
                                    placeholder="Contraseña P12"
                                />
                            </div>
                        </div>

                        <Button
                            variant="outline"
                            onClick={handleCertUpload}
                            disabled={
                                certForm.processing ||
                                !certForm.data.certificate
                            }
                        >
                            {certForm.processing
                                ? 'Procesando...'
                                : 'Subir y Convertir Certificado'}
                        </Button>
                    </CardContent>
                </Card>

                {/* Sequences */}
                <Card>
                    <CardHeader>
                        <CardTitle>Secuencias NCF</CardTitle>
                        <CardDescription>
                            Rangos de numeración autorizados por la DGII
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {sequences.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Sin secuencias registradas. Configura los rangos
                                autorizados por la DGII.
                            </p>
                        ) : (
                            sequences.map((seq) => (
                                <SequenceStatusRow
                                    key={seq.id}
                                    tipo={seq.tipo}
                                    available={seq.available}
                                    hasta={seq.hasta}
                                />
                            ))
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
