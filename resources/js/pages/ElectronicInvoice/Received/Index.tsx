import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { DgiiStatusBadge } from '@/components/electronic-invoice/dgii-status-badge';
import { EnvironmentBanner } from '@/components/electronic-invoice/environment-banner';
import { NcfFormatter } from '@/components/electronic-invoice/ncf-formatter';
import { DataTable, type Column } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedData } from '@/types/pagination';
import { Head, Link, router } from '@inertiajs/react';
import { FileInput, X } from 'lucide-react';

interface EcfReceived {
    id: number;
    numero_ecf: string;
    rnc_emisor: string | null;
    razon_social_emisor: string | null;
    monto_total: string;
    fecha_emision: string | null;
    status: string;
    arecf_sent_at: string | null;
    created_at: string;
}

interface Props {
    received: PaginatedData<EcfReceived>;
    config: { ambiente: string } | null;
    filters: { search?: string; status?: string };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Facturación Electrónica', href: '/admin/electronic-invoice/dashboard' },
    { title: 'e-CFs Recibidos', href: '/admin/electronic-invoice/received' },
];

function fmtDOP(amount: string): string {
    return Number(amount).toLocaleString('es-DO', {
        style: 'currency',
        currency: 'DOP',
        minimumFractionDigits: 0,
    });
}

const STATUSES = ['pending', 'accepted', 'rejected'];

export default function ReceivedIndex({ received, config, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    const handleSearch = (value: string) => {
        setSearch(value);
        router.get(
            '/admin/electronic-invoice/received',
            { ...filters, search: value || undefined },
            { preserveState: true, replace: true }
        );
    };

    const handleFilter = (key: string, value: string) => {
        router.get(
            '/admin/electronic-invoice/received',
            { ...filters, [key]: value === 'all' ? undefined : value },
            { preserveState: true, replace: true }
        );
    };

    const handleClearFilters = () => {
        setSearch('');
        router.get(
            '/admin/electronic-invoice/received',
            {},
            { preserveState: true, replace: true }
        );
    };

    const hasFilters = filters.search || filters.status;

    const columns: Column<EcfReceived>[] = [
        {
            key: 'numero_ecf',
            label: 'eNCF',
            render: (r) => (
                <Link
                    href={`/admin/electronic-invoice/received/${r.id}`}
                    className="font-mono text-xs text-primary hover:underline"
                >
                    <NcfFormatter ncf={r.numero_ecf} />
                </Link>
            ),
        },
        {
            key: 'emisor',
            label: 'Emisor / RNC',
            render: (r) => (
                <div>
                    <div className="font-medium text-foreground">
                        {r.razon_social_emisor ?? 'Emisor desconocido'}
                    </div>
                    {r.rnc_emisor && (
                        <div className="text-xs text-muted-foreground">
                            {r.rnc_emisor}
                        </div>
                    )}
                </div>
            ),
        },
        {
            key: 'monto_total',
            label: 'Monto',
            render: (r) => (
                <span className="font-medium">{fmtDOP(r.monto_total)}</span>
            ),
        },
        {
            key: 'fecha_emision',
            label: 'Recibido',
            render: (r) => (
                <span className="text-sm text-muted-foreground">
                    {r.fecha_emision
                        ? new Date(r.fecha_emision).toLocaleDateString('es-DO')
                        : '—'}
                </span>
            ),
        },
        {
            key: 'status',
            label: 'Estado',
            render: (r) => <DgiiStatusBadge status={r.status} />,
        },
        {
            key: 'actions',
            label: '',
            render: (r) => (
                <div className="flex items-center gap-2">
                    <Button variant="outline" size="sm" asChild>
                        <Link
                            href={`/admin/electronic-invoice/received/${r.id}`}
                        >
                            Ver
                        </Link>
                    </Button>
                    {r.status === 'pending' && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                router.post(
                                    `/admin/electronic-invoice/received/${r.id}/approve`,
                                    {}
                                )
                            }
                        >
                            Aprobar
                        </Button>
                    )}
                </div>
            ),
        },
    ];

    return (
        <AppLayout title="e-CFs Recibidos" breadcrumbs={breadcrumbs}>
            <Head title="e-CFs Recibidos" />
            <div className="mx-auto max-w-7xl space-y-6">
                {config && <EnvironmentBanner ambiente={config.ambiente} />}

                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight text-foreground">
                            e-CFs Recibidos
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Facturas de proveedores recibidas via DGII
                        </p>
                    </div>
                </div>

                {/* Filters */}
                <div className="rounded-lg border border-border bg-card p-4">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Input
                            placeholder="Buscar RNC emisor o NCF..."
                            value={search}
                            onChange={(e) => handleSearch(e.target.value)}
                        />
                        <Select
                            value={filters.status ?? ''}
                            onValueChange={(v) => handleFilter('status', v)}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Estado de aprobación" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todos los estados</SelectItem>
                                {STATUSES.map((s) => (
                                    <SelectItem key={s} value={s}>
                                        {s}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {hasFilters && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={handleClearFilters}
                            >
                                <X className="mr-2 h-4 w-4" />
                                Limpiar filtros
                            </Button>
                        )}
                    </div>
                </div>

                {/* Table */}
                {received.data.length === 0 ? (
                    <EmptyState
                        icon={FileInput}
                        title={
                            hasFilters
                                ? 'Sin resultados'
                                : 'Sin e-CFs recibidos'
                        }
                        description={
                            hasFilters
                                ? 'Ajusta los filtros para encontrar lo que buscas.'
                                : 'Los e-CFs de proveedores enviados a tu negocio aparecerán aquí.'
                        }
                    />
                ) : (
                    <DataTable
                        columns={columns}
                        data={received.data}
                        pagination={{
                            currentPage: received.current_page,
                            lastPage: received.last_page,
                            onPageChange: (page) =>
                                router.get(
                                    '/admin/electronic-invoice/received',
                                    { ...filters, page },
                                    { preserveState: true, replace: true }
                                ),
                        }}
                    />
                )}
            </div>
        </AppLayout>
    );
}
