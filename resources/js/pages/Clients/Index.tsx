import { router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { DataTable, type Column } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import type { User, PaginatedResponse, Appointment } from '@/types/models';
import { Users, Plus, Eye, Search } from 'lucide-react';
import { format } from 'date-fns';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { BlockModal } from './components/BlockModal';
import { UnblockModal } from './components/UnblockModal';
import { usePage } from '@inertiajs/react';

interface ClientWithStats extends User {
    appointments_count: number;
    stamps_count: number;
    appointments: Appointment[];
    pivot_status: 'active' | 'blocked' | 'left' | null;
    blocked_reason: string | null;
}

interface Filters {
    search?: string;
    sort?: string;
    direction?: 'asc' | 'desc';
    status_filter?: 'all' | 'active' | 'blocked';
}

interface Props {
    clients: PaginatedResponse<ClientWithStats>;
    filters: Filters;
    can: {
        create: boolean;
        block: boolean;
    };
}

const STATUS_OPTIONS = [
    { value: 'all', label: 'Todos' },
    { value: 'active', label: 'Solo activos' },
    { value: 'blocked', label: 'Solo bloqueados' },
] as const;

export default function ClientsIndex({ clients, filters, can }: Props) {
    const { t } = useTranslation();
    const { props } = usePage();
    const businessId = (props.auth as { user: { business_id: number } })?.user?.business_id;

    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const [blockingClient, setBlockingClient] = useState<ClientWithStats | null>(null);
    const [unblockingClient, setUnblockingClient] = useState<ClientWithStats | null>(null);

    const handleSearch = (value: string) => {
        setSearchQuery(value);
        router.get(
            '/clients',
            { ...filters, search: value || undefined },
            { preserveState: true, replace: true }
        );
    };

    const handleStatusFilter = (value: string) => {
        router.get(
            '/clients',
            { ...filters, status_filter: value === 'all' ? undefined : value },
            { preserveState: true, replace: true }
        );
    };

    const columns: Column<ClientWithStats>[] = [
        {
            key: 'name',
            label: t('clients.col_name'),
            sortable: true,
            render: (client) => (
                <div>
                    <div className="flex items-center gap-2">
                        <span className="font-medium text-foreground">{client.name}</span>
                        {client.role === 'lead' && (
                            <Badge variant="secondary">{t('clients.lead_badge')}</Badge>
                        )}
                    </div>
                    <div className="text-sm text-muted-foreground">{client.email}</div>
                </div>
            ),
        },
        {
            key: 'phone',
            label: t('clients.col_phone'),
            render: (client) => (
                <span className="text-sm text-muted-foreground">
                    {client.phone || '—'}
                </span>
            ),
        },
        {
            key: 'total_visits',
            label: t('clients.col_visits'),
            render: (client) => (
                <span className="text-sm font-medium">{client.appointments_count || 0}</span>
            ),
        },
        {
            key: 'stamps',
            label: t('clients.col_stamps'),
            render: (client) => (
                <span className="text-sm font-medium">{client.stamps_count || 0}</span>
            ),
        },
        {
            key: 'last_visit',
            label: t('clients.col_last_visit'),
            render: (client) => {
                const lastAppointment = client.appointments?.[0];
                return lastAppointment ? (
                    <span className="text-sm text-muted-foreground">
                        {format(new Date(lastAppointment.scheduled_at), 'dd/MM/yyyy')}
                    </span>
                ) : (
                    <span className="text-sm text-muted-foreground">—</span>
                );
            },
        },
        {
            key: 'status',
            label: 'Estado',
            render: (client) => {
                if (client.pivot_status === 'blocked') {
                    return (
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <span className="inline-flex">
                                    <Badge variant="destructive">Bloqueado</Badge>
                                </span>
                            </TooltipTrigger>
                            {client.blocked_reason && (
                                <TooltipContent>
                                    <p className="max-w-xs">{client.blocked_reason}</p>
                                </TooltipContent>
                            )}
                        </Tooltip>
                    );
                }

                if (client.pivot_status === 'active') {
                    return <Badge variant="default">Activo</Badge>;
                }

                return <span className="text-sm text-muted-foreground">—</span>;
            },
        },
        {
            key: 'actions',
            label: '',
            render: (client) => (
                <div className="flex items-center justify-end gap-2">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => router.get(`/clients/${client.id}`)}
                    >
                        <Eye className="h-4 w-4" />
                        {t('common.view')}
                    </Button>
                    {can.block && client.pivot_status === 'active' && (
                        <Button
                            variant="outline"
                            size="sm"
                            className="text-destructive border-destructive hover:bg-destructive hover:text-destructive-foreground"
                            onClick={() => setBlockingClient(client)}
                        >
                            Bloquear
                        </Button>
                    )}
                    {can.block && client.pivot_status === 'blocked' && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setUnblockingClient(client)}
                        >
                            Desbloquear
                        </Button>
                    )}
                </div>
            ),
        },
    ];

    return (
        <AppLayout
            title={t('clients.title')}
            breadcrumbs={[
                { label: t('breadcrumbs.dashboard'), href: '/dashboard' },
                { label: t('breadcrumbs.clients') },
            ]}
        >
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight text-foreground flex items-center gap-2">
                            <Users className="h-8 w-8" />
                            {t('clients.title')}
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {can.create ? t('clients.subtitle_admin') : t('clients.subtitle_employee')}
                        </p>
                    </div>
                    {can.create && (
                        <Button onClick={() => router.get('/clients/create')}>
                            <Plus className="mr-2 h-4 w-4" />
                            {t('clients.new')}
                        </Button>
                    )}
                </div>

                {/* Search + Status Filter */}
                <div className="flex gap-3">
                    <div className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder={t('common.search') + '...'}
                            value={searchQuery}
                            onChange={(e) => handleSearch(e.target.value)}
                            className="pl-9"
                        />
                    </div>
                    <Select
                        value={filters.status_filter ?? 'all'}
                        onValueChange={handleStatusFilter}
                    >
                        <SelectTrigger className="w-44">
                            <SelectValue placeholder="Estado" />
                        </SelectTrigger>
                        <SelectContent>
                            {STATUS_OPTIONS.map((opt) => (
                                <SelectItem key={opt.value} value={opt.value}>
                                    {opt.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {/* Data Table */}
                {clients.data.length > 0 ? (
                    <DataTable
                        data={clients.data}
                        columns={columns}
                        pagination={{
                            currentPage: clients.current_page,
                            lastPage: clients.last_page,
                            onPageChange: (page) =>
                                router.get('/clients', { ...filters, page }, { preserveState: true, replace: true }),
                        }}
                    />
                ) : (
                    <EmptyState
                        icon={Users}
                        title={t('clients.empty_title')}
                        description={
                            can.create
                                ? t('clients.empty_description_admin')
                                : t('clients.empty_description_employee')
                        }
                        action={
                            can.create
                                ? {
                                      label: t('clients.add_first'),
                                      onClick: () => router.get('/clients/create'),
                                  }
                                : undefined
                        }
                    />
                )}
            </div>

            {/* Block Modal */}
            <BlockModal
                client={blockingClient}
                businessId={businessId}
                open={blockingClient !== null}
                onClose={() => setBlockingClient(null)}
            />

            {/* Unblock Modal */}
            <UnblockModal
                client={unblockingClient}
                businessId={businessId}
                open={unblockingClient !== null}
                onClose={() => setUnblockingClient(null)}
            />
        </AppLayout>
    );
}
