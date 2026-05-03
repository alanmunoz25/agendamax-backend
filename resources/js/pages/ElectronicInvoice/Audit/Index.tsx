import AppLayout from '@/layouts/app-layout';
import { DataTable, type Column } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
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
import { Head, router } from '@inertiajs/react';
import { ClipboardList, X } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';

interface AuditLog {
    id: number;
    ecf_id: number | null;
    action: string;
    status_code: number | null;
    error: string | null;
    duration_ms: number | null;
    created_at: string;
}

interface Props {
    logs: PaginatedData<AuditLog>;
    actions: string[];
    filters: { action?: string; ecf_id?: string };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Facturación Electrónica', href: '/admin/electronic-invoice/dashboard' },
    { title: 'Auditoría FE' },
];

export default function AuditIndex({ logs, actions, filters }: Props) {
    const [ecfSearch, setEcfSearch] = useState(filters.ecf_id ?? '');

    const handleFilter = (key: string, value: string) => {
        router.get(
            '/admin/electronic-invoice/audit',
            { ...filters, [key]: value === 'all' ? undefined : value || undefined },
            { preserveState: true, replace: true }
        );
    };

    const handleClearFilters = () => {
        setEcfSearch('');
        router.get(
            '/admin/electronic-invoice/audit',
            {},
            { preserveState: true, replace: true }
        );
    };

    const hasFilters = filters.action || filters.ecf_id;

    const columns: Column<AuditLog>[] = [
        {
            key: 'created_at',
            label: 'Fecha',
            render: (log) => (
                <span className="text-sm text-muted-foreground">
                    {new Date(log.created_at).toLocaleString('es-DO')}
                </span>
            ),
        },
        {
            key: 'action',
            label: 'Acción',
            render: (log) => (
                <span className="font-mono text-xs font-medium text-foreground">
                    {log.action}
                </span>
            ),
        },
        {
            key: 'ecf_id',
            label: 'e-CF ID',
            render: (log) =>
                log.ecf_id ? (
                    <a
                        href={`/admin/electronic-invoice/issued/${log.ecf_id}`}
                        className="text-xs text-primary hover:underline"
                    >
                        #{log.ecf_id}
                    </a>
                ) : (
                    <span className="text-muted-foreground">—</span>
                ),
        },
        {
            key: 'status_code',
            label: 'HTTP',
            render: (log) =>
                log.status_code ? (
                    <Badge
                        variant={
                            log.status_code >= 200 && log.status_code < 300
                                ? 'outline'
                                : 'destructive'
                        }
                    >
                        {log.status_code}
                    </Badge>
                ) : (
                    <span className="text-muted-foreground">—</span>
                ),
        },
        {
            key: 'error',
            label: 'Error',
            render: (log) =>
                log.error ? (
                    <span className="max-w-xs truncate text-xs text-destructive">
                        {log.error}
                    </span>
                ) : (
                    <span className="text-xs text-[var(--color-green-brand)]">
                        OK
                    </span>
                ),
        },
        {
            key: 'duration_ms',
            label: 'Duración',
            render: (log) => (
                <span className="text-xs text-muted-foreground">
                    {log.duration_ms != null ? `${log.duration_ms} ms` : '—'}
                </span>
            ),
        },
    ];

    return (
        <AppLayout title="Auditoría FE" breadcrumbs={breadcrumbs}>
            <Head title="Auditoría de Facturación Electrónica" />
            <div className="mx-auto max-w-7xl space-y-6">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight text-foreground">
                        Auditoría FE
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Registro completo de operaciones de facturación
                        electrónica
                    </p>
                </div>

                {/* Filters */}
                <div className="rounded-lg border border-border bg-card p-4">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Select
                            value={filters.action ?? ''}
                            onValueChange={(v) => handleFilter('action', v)}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Filtrar por acción" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    Todas las acciones
                                </SelectItem>
                                {actions.map((a) => (
                                    <SelectItem key={a} value={a}>
                                        {a}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Input
                            placeholder="Filtrar por ID de e-CF..."
                            value={ecfSearch}
                            type="number"
                            onChange={(e) => {
                                setEcfSearch(e.target.value);
                                handleFilter('ecf_id', e.target.value);
                            }}
                        />
                        {hasFilters && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={handleClearFilters}
                            >
                                <X className="mr-2 h-4 w-4" />
                                Limpiar
                            </Button>
                        )}
                    </div>
                </div>

                {/* Table */}
                {logs.data.length === 0 ? (
                    <EmptyState
                        icon={ClipboardList}
                        title="Sin registros de auditoría"
                        description="Las operaciones de facturación electrónica se registrarán aquí."
                    />
                ) : (
                    <DataTable
                        columns={columns}
                        data={logs.data}
                        pagination={{
                            currentPage: logs.current_page,
                            lastPage: logs.last_page,
                            onPageChange: (page) =>
                                router.get(
                                    '/admin/electronic-invoice/audit',
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
