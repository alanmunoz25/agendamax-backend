import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { DgiiStatusBadge } from '@/components/electronic-invoice/dgii-status-badge';
import { EcfTypeBadge } from '@/components/electronic-invoice/ecf-type-badge';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedData } from '@/types/pagination';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, FileText, ChevronDown, X } from 'lucide-react';

interface Ecf {
    id: number;
    numero_ecf: string;
    tipo: string;
    rnc_comprador: string | null;
    razon_social_comprador: string | null;
    monto_total: string;
    status: string;
    fecha_emision: string | null;
    created_at: string;
}

interface Props {
    ecfs: PaginatedData<Ecf>;
    config: { ambiente: string; activo: boolean } | null;
    filters: { search?: string; status?: string; tipo?: string };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Facturación Electrónica', href: '/admin/electronic-invoice/dashboard' },
    { title: 'e-CFs Emitidos', href: '/admin/electronic-invoice/issued' },
];

function fmtDOP(amount: string): string {
    return Number(amount).toLocaleString('es-DO', {
        style: 'currency',
        currency: 'DOP',
        minimumFractionDigits: 0,
    });
}

const TIPOS = ['31', '32', '33', '34'];
const STATUSES = ['draft', 'signed', 'sent', 'accepted', 'rejected', 'error'];

export default function IssuedIndex({ ecfs, config, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    const handleSearch = (value: string) => {
        setSearch(value);
        router.get(
            '/admin/electronic-invoice/issued',
            { ...filters, search: value || undefined },
            { preserveState: true, replace: true }
        );
    };

    const handleFilter = (key: string, value: string) => {
        router.get(
            '/admin/electronic-invoice/issued',
            { ...filters, [key]: value === 'all' ? undefined : value },
            { preserveState: true, replace: true }
        );
    };

    const handleClearFilters = () => {
        setSearch('');
        router.get(
            '/admin/electronic-invoice/issued',
            {},
            { preserveState: true, replace: true }
        );
    };

    const hasFilters = filters.search || filters.status || filters.tipo;

    const columns: Column<Ecf>[] = [
        {
            key: 'numero_ecf',
            label: 'eNCF',
            render: (ecf) => (
                <Link
                    href={`/admin/electronic-invoice/issued/${ecf.id}`}
                    className="font-mono text-xs text-primary hover:underline"
                >
                    <NcfFormatter ncf={ecf.numero_ecf} />
                </Link>
            ),
        },
        {
            key: 'tipo',
            label: 'Tipo',
            render: (ecf) => <EcfTypeBadge tipo={ecf.tipo} />,
        },
        {
            key: 'client',
            label: 'Cliente / RNC',
            render: (ecf) => (
                <div>
                    <div className="font-medium text-foreground">
                        {ecf.razon_social_comprador ?? 'Cliente General'}
                    </div>
                    {ecf.rnc_comprador && (
                        <div className="text-xs text-muted-foreground">
                            {ecf.rnc_comprador}
                        </div>
                    )}
                </div>
            ),
        },
        {
            key: 'monto_total',
            label: 'Monto',
            render: (ecf) => (
                <span className="font-medium">{fmtDOP(ecf.monto_total)}</span>
            ),
        },
        {
            key: 'status',
            label: 'Status DGII',
            render: (ecf) => <DgiiStatusBadge status={ecf.status} />,
        },
        {
            key: 'actions',
            label: '',
            render: (ecf) => (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" size="sm">
                            <ChevronDown className="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem asChild>
                            <Link
                                href={`/admin/electronic-invoice/issued/${ecf.id}`}
                            >
                                Ver detalle
                            </Link>
                        </DropdownMenuItem>
                        {ecf.status === 'accepted' && (
                            <>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    onClick={() =>
                                        router.post(
                                            `/admin/electronic-invoice/issued/${ecf.id}/credit-note`,
                                            {}
                                        )
                                    }
                                >
                                    Emitir Nota de Crédito
                                </DropdownMenuItem>
                            </>
                        )}
                        {(ecf.status === 'rejected' ||
                            ecf.status === 'error') && (
                            <>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    onClick={() =>
                                        router.post(
                                            `/admin/electronic-invoice/issued/${ecf.id}/resend`,
                                            {}
                                        )
                                    }
                                >
                                    Reenviar a DGII
                                </DropdownMenuItem>
                            </>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            ),
        },
    ];

    return (
        <AppLayout title="e-CFs Emitidos" breadcrumbs={breadcrumbs}>
            <Head title="e-CFs Emitidos" />
            <div className="mx-auto max-w-7xl space-y-6">
                {config && <EnvironmentBanner ambiente={config.ambiente} />}

                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight text-foreground">
                            e-CFs Emitidos
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Comprobantes fiscales electrónicos enviados a DGII
                        </p>
                    </div>
                    <Button
                        onClick={() =>
                            router.visit(
                                '/admin/electronic-invoice/issued/create'
                            )
                        }
                    >
                        <Plus className="mr-2 h-4 w-4" />
                        Emitir e-CF
                    </Button>
                </div>

                {/* Filters */}
                <div className="rounded-lg border border-border bg-card p-4">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Input
                            placeholder="Buscar NCF o RNC..."
                            value={search}
                            onChange={(e) => handleSearch(e.target.value)}
                        />
                        <Select
                            value={filters.tipo ?? ''}
                            onValueChange={(v) => handleFilter('tipo', v)}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Tipo de comprobante" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todos los tipos</SelectItem>
                                {TIPOS.map((t) => (
                                    <SelectItem key={t} value={t}>
                                        Tipo {t}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select
                            value={filters.status ?? ''}
                            onValueChange={(v) => handleFilter('status', v)}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Estado DGII" />
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
                {ecfs.data.length === 0 ? (
                    <EmptyState
                        icon={FileText}
                        title={hasFilters ? 'Sin resultados' : 'Sin e-CFs emitidos'}
                        description={
                            hasFilters
                                ? 'Ajusta los filtros para encontrar lo que buscas.'
                                : 'Emite el primer comprobante fiscal electrónico manualmente o completa un appointment.'
                        }
                        action={
                            !hasFilters
                                ? {
                                      label: '+ Emitir e-CF',
                                      onClick: () =>
                                          router.visit(
                                              '/admin/electronic-invoice/issued/create'
                                          ),
                                  }
                                : undefined
                        }
                    />
                ) : (
                    <DataTable
                        columns={columns}
                        data={ecfs.data}
                        pagination={{
                            currentPage: ecfs.current_page,
                            lastPage: ecfs.last_page,
                            onPageChange: (page) =>
                                router.get(
                                    '/admin/electronic-invoice/issued',
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
