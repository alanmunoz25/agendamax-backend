import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { DgiiErrorDetails } from '@/components/electronic-invoice/dgii-error-details';
import { DgiiStatusBadge } from '@/components/electronic-invoice/dgii-status-badge';
import { EcfTypeBadge } from '@/components/electronic-invoice/ecf-type-badge';
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
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    CheckCircle,
    Copy,
    Download,
    FileText,
    XCircle,
} from 'lucide-react';

interface EcfItem {
    description: string;
    qty: number;
    unit_price: string;
    line_total: string;
}

interface AuditLog {
    id: number;
    action: string;
    created_at: string;
    payload: Record<string, unknown> | null;
    status_code: number | null;
    error: string | null;
}

interface Ecf {
    id: number;
    numero_ecf: string;
    tipo_ecf: number;
    status: string;
    track_id: string | null;
    rnc_comprador: string | null;
    razon_social_comprador: string | null;
    fecha_emision: string | null;
    monto_total: string;
    itbis_total: string;
    xml_path: string | null;
    xml_content: string | null;
    error_message: string | null;
    appointment_id: number | null;
    created_at: string;
    manual_void_ncf: string | null;
    manual_void_reason: string | null;
    voided_at: string | null;
}

interface Props {
    ecf: Ecf;
    items: EcfItem[];
    audit_logs: AuditLog[];
    config: { ambiente: string };
    can: { emit_credit_note: boolean; resend: boolean; register_manual_void: boolean };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Facturación Electrónica', href: '/admin/electronic-invoice/dashboard' },
    { title: 'e-CFs Emitidos', href: '/admin/electronic-invoice/issued' },
    { title: 'Detalle' },
];

type ActiveTab = 'xml' | 'audit';

function fmtDOP(amount: string | number): string {
    return Number(amount).toLocaleString('es-DO', {
        style: 'currency',
        currency: 'DOP',
    });
}

export default function IssuedShow({ ecf, items, audit_logs, config, can }: Props) {
    const [activeTab, setActiveTab] = useState<ActiveTab>('xml');
    const [copied, setCopied] = useState(false);
    const [showManualVoidModal, setShowManualVoidModal] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        manual_void_ncf: '',
        reason: '',
    });

    function handleManualVoidSubmit(e: React.FormEvent) {
        e.preventDefault();
        post(`/admin/electronic-invoice/issued/${ecf.id}/register-manual-void`, {
            onSuccess: () => {
                setShowManualVoidModal(false);
                reset();
            },
        });
    }

    const handleCopyXml = () => {
        if (ecf.xml_content) {
            navigator.clipboard.writeText(ecf.xml_content);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        }
    };

    const handleDownloadXml = () => {
        if (!ecf.xml_content) return;
        const blob = new Blob([ecf.xml_content], { type: 'application/xml' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${ecf.numero_ecf}.xml`;
        a.click();
        URL.revokeObjectURL(url);
    };

    return (
        <AppLayout title={`e-CF ${ecf.numero_ecf}`} breadcrumbs={breadcrumbs}>
            <Head title={`e-CF ${ecf.numero_ecf}`} />
            <div className="mx-auto max-w-5xl space-y-6">
                <EnvironmentBanner ambiente={config.ambiente} />

                {/* Header */}
                <div className="flex items-start justify-between gap-4">
                    <div className="flex items-start gap-4">
                        <Link
                            href="/admin/electronic-invoice/issued"
                            className="mt-1 text-muted-foreground hover:text-foreground"
                        >
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                        <div>
                            <div className="flex items-center gap-3">
                                <h1 className="text-2xl font-bold tracking-tight text-foreground">
                                    eNCF:{' '}
                                    <NcfFormatter ncf={ecf.numero_ecf} />
                                </h1>
                                <DgiiStatusBadge status={ecf.status} />
                            </div>
                            <div className="mt-1 flex items-center gap-3 text-sm text-muted-foreground">
                                <EcfTypeBadge tipo={ecf.tipo_ecf} />
                                {ecf.fecha_emision && (
                                    <span>
                                        Emitido:{' '}
                                        {new Date(
                                            ecf.fecha_emision
                                        ).toLocaleDateString('es-DO')}
                                    </span>
                                )}
                                {ecf.track_id && (
                                    <span className="font-mono text-xs">
                                        TrackID: {ecf.track_id}
                                    </span>
                                )}
                            </div>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        {ecf.xml_content && (
                            <Button variant="outline" size="sm" onClick={handleDownloadXml}>
                                <Download className="mr-2 h-4 w-4" />
                                XML
                            </Button>
                        )}
                    </div>
                </div>

                {/* Error state */}
                {(ecf.status === 'rejected' || ecf.status === 'error') && (
                    <DgiiErrorDetails
                        errorMessage={ecf.error_message}
                        onResend={
                            can.resend
                                ? () =>
                                      router.post(
                                          `/admin/electronic-invoice/issued/${ecf.id}/resend`,
                                          {}
                                      )
                                : undefined
                        }
                    />
                )}

                <div className="grid gap-6 md:grid-cols-2">
                    {/* Buyer data */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Datos del Comprador
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    RNC/Cédula
                                </p>
                                <p className="font-mono font-medium">
                                    {ecf.rnc_comprador ?? '—'}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Razón Social
                                </p>
                                <p className="font-medium">
                                    {ecf.razon_social_comprador ?? 'Cliente General'}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Totals */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Totales
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">
                                    ITBIS (18%)
                                </span>
                                <span>{fmtDOP(ecf.itbis_total)}</span>
                            </div>
                            <Separator />
                            <div className="flex justify-between font-semibold text-base">
                                <span>Total</span>
                                <span>{fmtDOP(ecf.monto_total)}</span>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Items */}
                {items.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Líneas
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-border text-left text-xs text-muted-foreground">
                                        <th className="pb-2 font-medium">Descripción</th>
                                        <th className="pb-2 text-right font-medium">Cant.</th>
                                        <th className="pb-2 text-right font-medium">P. Unitario</th>
                                        <th className="pb-2 text-right font-medium">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {items.map((item, i) => (
                                        <tr
                                            key={i}
                                            className="border-b border-border/50 last:border-0"
                                        >
                                            <td className="py-2 pr-4">
                                                {item.description}
                                            </td>
                                            <td className="py-2 pr-4 text-right">
                                                {item.qty}
                                            </td>
                                            <td className="py-2 pr-4 text-right">
                                                {fmtDOP(item.unit_price)}
                                            </td>
                                            <td className="py-2 text-right font-medium">
                                                {fmtDOP(item.line_total)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                )}

                {/* Tabs */}
                <div>
                    <div className="flex gap-1 border-b border-border">
                        {(['xml', 'audit'] as const).map((tab) => (
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
                                {tab === 'xml' ? 'XML Enviado' : 'Auditoría'}
                            </button>
                        ))}
                    </div>

                    <div className="mt-4">
                        {activeTab === 'xml' && (
                            <Card>
                                <CardContent className="pt-4">
                                    {ecf.xml_content ? (
                                        <div className="space-y-3">
                                            <div className="flex justify-end gap-2">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={handleCopyXml}
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
                                                    onClick={handleDownloadXml}
                                                >
                                                    <Download className="mr-2 h-4 w-4" />
                                                    Descargar
                                                </Button>
                                            </div>
                                            <pre className="max-h-96 overflow-auto rounded-md bg-muted p-4 text-xs text-muted-foreground">
                                                {ecf.xml_content}
                                            </pre>
                                        </div>
                                    ) : (
                                        <p className="text-center text-sm text-muted-foreground">
                                            XML no disponible
                                        </p>
                                    )}
                                </CardContent>
                            </Card>
                        )}

                        {activeTab === 'audit' && (
                            <Card>
                                <CardContent className="pt-4">
                                    {audit_logs.length === 0 ? (
                                        <p className="text-center text-sm text-muted-foreground">
                                            Sin registros de auditoría
                                        </p>
                                    ) : (
                                        <div className="space-y-3">
                                            {audit_logs.map((log) => (
                                                <div
                                                    key={log.id}
                                                    className="flex items-start gap-3 border-b border-border/50 pb-3 last:border-0"
                                                >
                                                    <div className="mt-1 h-2 w-2 shrink-0 rounded-full bg-primary" />
                                                    <div className="flex-1 text-sm">
                                                        <div className="flex items-center gap-2">
                                                            <span className="font-medium text-foreground">
                                                                {log.action}
                                                            </span>
                                                            {log.status_code && (
                                                                <span className="text-xs text-muted-foreground">
                                                                    HTTP {log.status_code}
                                                                </span>
                                                            )}
                                                        </div>
                                                        <p className="text-xs text-muted-foreground">
                                                            {new Date(
                                                                log.created_at
                                                            ).toLocaleString('es-DO')}
                                                        </p>
                                                        {log.error && (
                                                            <p className="mt-1 text-xs text-destructive">
                                                                {log.error}
                                                            </p>
                                                        )}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>

                {/* Voided manual banner */}
                {ecf.status === 'voided_manual' && (
                    <div className="flex items-start gap-3 rounded-lg border border-border bg-card p-4">
                        <XCircle className="mt-0.5 h-5 w-5 shrink-0 text-destructive" />
                        <div className="flex-1 text-sm">
                            <p className="font-medium text-foreground">Anulación manual registrada</p>
                            <p className="text-muted-foreground">
                                NC tipo 34: <span className="font-mono">{ecf.manual_void_ncf}</span>
                            </p>
                            {ecf.manual_void_reason && (
                                <p className="text-muted-foreground">Razón: {ecf.manual_void_reason}</p>
                            )}
                            {ecf.voided_at && (
                                <p className="text-muted-foreground">
                                    {new Date(ecf.voided_at).toLocaleString('es-DO')}
                                </p>
                            )}
                        </div>
                    </div>
                )}

                {/* Actions */}
                {can.emit_credit_note && (
                    <div className="flex items-center gap-3 rounded-lg border border-border bg-card p-4">
                        <FileText className="h-5 w-5 text-muted-foreground" />
                        <div className="flex-1">
                            <p className="text-sm font-medium">
                                Emitir Nota de Crédito (Anular)
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Genera un nuevo e-CF tipo 34 referenciando este NCF
                            </p>
                        </div>
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.post(
                                    `/admin/electronic-invoice/issued/${ecf.id}/credit-note`,
                                    {}
                                )
                            }
                        >
                            Emitir NC
                        </Button>
                    </div>
                )}

                {/* Manual void instructions card */}
                {can.register_manual_void && (
                    <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/30">
                        <div className="flex items-start gap-3">
                            <AlertCircle className="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />
                            <div className="flex-1 space-y-2">
                                <p className="text-sm font-medium text-amber-900 dark:text-amber-100">
                                    Para anular este e-CF:
                                </p>
                                <ol className="list-decimal space-y-1 pl-4 text-sm text-amber-800 dark:text-amber-200">
                                    <li>
                                        Ingresa al{' '}
                                        <a
                                            href="https://dgii.gov.do"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="underline hover:no-underline"
                                        >
                                            portal DGII
                                        </a>
                                    </li>
                                    <li>
                                        Emite Nota de Crédito tipo 34 referenciando este NCF:{' '}
                                        <span className="font-mono font-semibold">{ecf.numero_ecf}</span>
                                    </li>
                                    <li>Una vez emitida, regístralo aquí:</li>
                                </ol>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    className="border-amber-400 bg-white text-amber-900 hover:bg-amber-100 dark:bg-transparent dark:text-amber-100 dark:hover:bg-amber-900/40"
                                    onClick={() => setShowManualVoidModal(true)}
                                >
                                    Registrar anulación manual
                                </Button>
                            </div>
                        </div>
                    </div>
                )}

                {/* RegisterManualVoid modal */}
                <Dialog open={showManualVoidModal} onOpenChange={setShowManualVoidModal}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Registrar anulación manual</DialogTitle>
                            <DialogDescription>
                                Ingresa los datos de la Nota de Crédito tipo 34 emitida en el portal DGII.
                            </DialogDescription>
                        </DialogHeader>
                        <form onSubmit={handleManualVoidSubmit} className="space-y-4">
                            <div className="space-y-1.5">
                                <Label htmlFor="manual_void_ncf">NCF de la Nota de Crédito (E34...)</Label>
                                <Input
                                    id="manual_void_ncf"
                                    placeholder="E34000000001"
                                    value={data.manual_void_ncf}
                                    onChange={(e) => setData('manual_void_ncf', e.target.value)}
                                    className="font-mono"
                                />
                                {errors.manual_void_ncf && (
                                    <p className="text-sm text-destructive">{errors.manual_void_ncf}</p>
                                )}
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="reason">Razón de anulación</Label>
                                <Textarea
                                    id="reason"
                                    placeholder="Describe el motivo de la anulación..."
                                    rows={3}
                                    value={data.reason}
                                    onChange={(e) => setData('reason', e.target.value)}
                                />
                                {errors.reason && (
                                    <p className="text-sm text-destructive">{errors.reason}</p>
                                )}
                            </div>
                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => { setShowManualVoidModal(false); reset(); }}
                                >
                                    Cancelar
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={processing || data.manual_void_ncf.length === 0 || data.reason.length < 10}
                                >
                                    {processing ? 'Registrando...' : 'Registrar anulación'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
