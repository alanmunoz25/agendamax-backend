import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { DgiiStatusBadge } from '@/components/electronic-invoice/dgii-status-badge';
import { EnvironmentBanner } from '@/components/electronic-invoice/environment-banner';
import { NcfFormatter } from '@/components/electronic-invoice/ncf-formatter';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Copy, CheckCircle, Download } from 'lucide-react';

interface EcfReceived {
    id: number;
    rnc_emisor: string | null;
    razon_social_emisor: string | null;
    numero_ecf: string;
    tipo: string | null;
    fecha_emision: string | null;
    monto_total: string;
    itbis_total: string;
    status: string;
    arecf_sent_at: string | null;
    xml_path: string | null;
    xml_content: string | null;
    arecf_content: string | null;
}

interface Props {
    received: EcfReceived;
    config: { ambiente: string };
    can: { approve: boolean; reject: boolean };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Facturación Electrónica', href: '/admin/electronic-invoice/dashboard' },
    { title: 'e-CFs Recibidos', href: '/admin/electronic-invoice/received' },
    { title: 'Detalle' },
];

const REJECT_CODES = [
    { value: '1', label: '1 — No reconoce la operación' },
    { value: '2', label: '2 — Datos incorrectos' },
    { value: '3', label: '3 — Factura duplicada' },
    { value: '4', label: '4 — Otro motivo' },
];

type ActiveTab = 'xml' | 'arecf';

function fmtDOP(amount: string): string {
    return Number(amount).toLocaleString('es-DO', {
        style: 'currency',
        currency: 'DOP',
    });
}

export default function ReceivedShow({ received, config, can }: Props) {
    const [activeTab, setActiveTab] = useState<ActiveTab>('xml');
    const [showRejectModal, setShowRejectModal] = useState(false);
    const [copied, setCopied] = useState(false);

    const rejectForm = useForm({
        codigo_rechazo: '',
        razon: '',
    });

    const handleApprove = () => {
        router.post(
            `/admin/electronic-invoice/received/${received.id}/approve`,
            {}
        );
    };

    const handleReject = () => {
        rejectForm.post(
            `/admin/electronic-invoice/received/${received.id}/reject`,
            {
                onSuccess: () => setShowRejectModal(false),
            }
        );
    };

    const xmlToShow = activeTab === 'xml' ? received.xml_content : received.arecf_content;

    const handleCopy = () => {
        if (xmlToShow) {
            navigator.clipboard.writeText(xmlToShow);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        }
    };

    const handleDownload = () => {
        if (!xmlToShow) return;
        const blob = new Blob([xmlToShow], { type: 'application/xml' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${received.numero_ecf}_${activeTab}.xml`;
        a.click();
        URL.revokeObjectURL(url);
    };

    return (
        <AppLayout
            title={`e-CF Recibido ${received.numero_ecf}`}
            breadcrumbs={breadcrumbs}
        >
            <Head title={`e-CF Recibido ${received.numero_ecf}`} />
            <div className="mx-auto max-w-4xl space-y-6">
                <EnvironmentBanner ambiente={config.ambiente} />

                {/* Header */}
                <div className="flex items-start gap-4">
                    <Link
                        href="/admin/electronic-invoice/received"
                        className="mt-1 text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft className="h-5 w-5" />
                    </Link>
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-bold tracking-tight text-foreground">
                                eNCF:{' '}
                                <NcfFormatter ncf={received.numero_ecf} />
                            </h1>
                            <DgiiStatusBadge status={received.status} />
                        </div>
                        {received.fecha_emision && (
                            <p className="mt-1 text-sm text-muted-foreground">
                                Recibido:{' '}
                                {new Date(
                                    received.fecha_emision
                                ).toLocaleString('es-DO')}
                            </p>
                        )}
                    </div>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    {/* Emisor */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Datos del Proveedor (Emisor)
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            <div>
                                <p className="text-xs text-muted-foreground">RNC</p>
                                <p className="font-mono font-medium">
                                    {received.rnc_emisor ?? '—'}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Razón Social
                                </p>
                                <p className="font-medium">
                                    {received.razon_social_emisor ?? '—'}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    {/* ARECF info */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                ARECF
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Acuse de Recibo enviado
                                </p>
                                <p className="font-medium">
                                    {received.arecf_sent_at
                                        ? new Date(
                                              received.arecf_sent_at
                                          ).toLocaleString('es-DO')
                                        : 'Pendiente'}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Amounts */}
                <Card>
                    <CardContent className="pt-4">
                        <div className="flex flex-wrap gap-6 text-sm">
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Monto Total
                                </p>
                                <p className="text-lg font-bold">
                                    {fmtDOP(received.monto_total)}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    ITBIS
                                </p>
                                <p className="text-lg font-semibold">
                                    {fmtDOP(received.itbis_total)}
                                </p>
                            </div>
                            {received.fecha_emision && (
                                <div>
                                    <p className="text-xs text-muted-foreground">
                                        Fecha emisión
                                    </p>
                                    <p className="text-lg font-semibold">
                                        {new Date(
                                            received.fecha_emision
                                        ).toLocaleDateString('es-DO')}
                                    </p>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Approval actions */}
                {(can.approve || can.reject) && (
                    <Card className="border-blue-200 bg-blue-50/50 dark:border-blue-800 dark:bg-blue-950/20">
                        <CardContent className="pt-4">
                            <p className="text-sm font-medium text-foreground">
                                ¿Aceptas comercialmente esta factura de{' '}
                                {received.razon_social_emisor ?? 'este proveedor'}?
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Al aprobar, se enviará un ACECF (Aprobación
                                Comercial) a DGII.
                            </p>
                            <div className="mt-4 flex gap-3">
                                {can.approve && (
                                    <Button onClick={handleApprove}>
                                        Aprobar Comercialmente →
                                    </Button>
                                )}
                                {can.reject && (
                                    <Button
                                        variant="outline"
                                        onClick={() =>
                                            setShowRejectModal(true)
                                        }
                                    >
                                        Rechazar
                                    </Button>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* XML Tabs */}
                <div>
                    <div className="flex gap-1 border-b border-border">
                        {(['xml', 'arecf'] as const).map((tab) => (
                            <button
                                key={tab}
                                type="button"
                                onClick={() => setActiveTab(tab)}
                                className={`px-4 py-2 text-sm font-medium transition-colors ${
                                    activeTab === tab
                                        ? 'border-b-2 border-primary text-primary'
                                        : 'text-muted-foreground hover:text-foreground'
                                }`}
                            >
                                {tab === 'xml'
                                    ? 'XML Original'
                                    : 'ARECF Generado'}
                            </button>
                        ))}
                    </div>

                    <div className="mt-4">
                        <Card>
                            <CardContent className="pt-4">
                                {xmlToShow ? (
                                    <div className="space-y-3">
                                        <div className="flex justify-end gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={handleCopy}
                                            >
                                                {copied ? (
                                                    <>
                                                        <CheckCircle className="mr-2 h-4 w-4 text-green-600" />
                                                        Copiado
                                                    </>
                                                ) : (
                                                    <>
                                                        <Copy className="mr-2 h-4 w-4" />
                                                        Copiar
                                                    </>
                                                )}
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={handleDownload}
                                            >
                                                <Download className="mr-2 h-4 w-4" />
                                                Descargar
                                            </Button>
                                        </div>
                                        <pre className="max-h-80 overflow-auto rounded-md bg-muted p-4 text-xs text-muted-foreground">
                                            {xmlToShow}
                                        </pre>
                                    </div>
                                ) : (
                                    <p className="text-center text-sm text-muted-foreground">
                                        {activeTab === 'xml'
                                            ? 'XML original no disponible'
                                            : 'ARECF aún no generado'}
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>

            {/* Reject Modal */}
            <Dialog
                open={showRejectModal}
                onOpenChange={setShowRejectModal}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Rechazar Factura Recibida</DialogTitle>
                    </DialogHeader>
                    <div className="space-y-4 py-2">
                        <div className="rounded-md bg-muted p-3 text-sm">
                            <p>
                                <span className="text-muted-foreground">
                                    Emisor:
                                </span>{' '}
                                {received.razon_social_emisor}
                            </p>
                            <p>
                                <span className="text-muted-foreground">
                                    eNCF:
                                </span>{' '}
                                <span className="font-mono">
                                    {received.numero_ecf}
                                </span>
                            </p>
                        </div>

                        <div className="space-y-2">
                            <Label>Código de rechazo *</Label>
                            <Select
                                value={rejectForm.data.codigo_rechazo}
                                onValueChange={(v) =>
                                    rejectForm.setData(
                                        'codigo_rechazo',
                                        v
                                    )
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Seleccionar código..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {REJECT_CODES.map((c) => (
                                        <SelectItem
                                            key={c.value}
                                            value={c.value}
                                        >
                                            {c.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {rejectForm.errors.codigo_rechazo && (
                                <p className="text-xs text-destructive">
                                    {rejectForm.errors.codigo_rechazo}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label>Razón adicional (opcional)</Label>
                            <Textarea
                                value={rejectForm.data.razon}
                                onChange={(e) =>
                                    rejectForm.setData(
                                        'razon',
                                        e.target.value
                                    )
                                }
                                placeholder="Descripción adicional..."
                                rows={3}
                            />
                        </div>

                        <p className="flex items-center gap-1.5 rounded-md bg-blue-50 p-3 text-xs text-blue-700 dark:bg-blue-950/30 dark:text-blue-300">
                            Se enviará un ACECF de rechazo a DGII.
                        </p>
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setShowRejectModal(false)}
                        >
                            Cancelar
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={handleReject}
                            disabled={
                                rejectForm.processing ||
                                !rejectForm.data.codigo_rechazo
                            }
                        >
                            {rejectForm.processing
                                ? 'Rechazando...'
                                : 'Confirmar Rechazo'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
