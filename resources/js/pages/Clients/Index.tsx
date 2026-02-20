import { router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { DataTable, type Column } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import type { User, PaginatedResponse, Appointment } from '@/types/models';
import { Users, Plus, Eye, Search } from 'lucide-react';
import { format } from 'date-fns';
import { useState } from 'react';

interface ClientWithStats extends User {
    appointments_count: number;
    stamps_count: number;
    appointments: Appointment[];
}

interface Filters {
    search?: string;
    sort?: string;
    direction?: 'asc' | 'desc';
}

interface Props {
    clients: PaginatedResponse<ClientWithStats>;
    filters: Filters;
    can: {
        create: boolean;
    };
}

export default function ClientsIndex({ clients, filters, can }: Props) {
    const [searchQuery, setSearchQuery] = useState(filters.search || '');

    const handleSearch = (value: string) => {
        setSearchQuery(value);
        router.get(
            '/clients',
            { ...filters, search: value || undefined },
            { preserveState: true, replace: true }
        );
    };

    const columns: Column<ClientWithStats>[] = [
        {
            key: 'name',
            label: 'Client Name',
            sortable: true,
            render: (client) => (
                <div>
                    <div className="flex items-center gap-2">
                        <span className="font-medium text-foreground">{client.name}</span>
                        {client.role === 'lead' && (
                            <Badge variant="secondary">Lead</Badge>
                        )}
                    </div>
                    <div className="text-sm text-muted-foreground">{client.email}</div>
                </div>
            ),
        },
        {
            key: 'phone',
            label: 'Phone',
            render: (client) => (
                <span className="text-sm text-muted-foreground">
                    {client.phone || '—'}
                </span>
            ),
        },
        {
            key: 'total_visits',
            label: 'Visits',
            render: (client) => (
                <span className="text-sm font-medium">{client.appointments_count || 0}</span>
            ),
        },
        {
            key: 'stamps',
            label: 'Stamps',
            render: (client) => (
                <span className="text-sm font-medium">{client.stamps_count || 0}</span>
            ),
        },
        {
            key: 'last_visit',
            label: 'Last Visit',
            render: (client) => {
                const lastAppointment = client.appointments?.[0];
                return lastAppointment ? (
                    <span className="text-sm text-muted-foreground">
                        {format(new Date(lastAppointment.scheduled_at), 'MMM d, yyyy')}
                    </span>
                ) : (
                    <span className="text-sm text-muted-foreground">Never</span>
                );
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
                        View
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <AppLayout
            title="Clients"
            breadcrumbs={[
                { label: 'Dashboard', href: '/dashboard' },
                { label: 'Clients' },
            ]}
        >
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight text-foreground flex items-center gap-2">
                            <Users className="h-8 w-8" />
                            Clients
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {can.create ? 'Manage your client database and loyalty progress' : 'View your assigned clients'}
                        </p>
                    </div>
                    {can.create && (
                        <Button onClick={() => router.get('/clients/create')}>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Client
                        </Button>
                    )}
                </div>

                {/* Search */}
                <div className="relative">
                    <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        placeholder="Search by name, email, or phone..."
                        value={searchQuery}
                        onChange={(e) => handleSearch(e.target.value)}
                        className="pl-9"
                    />
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
                        title="No clients yet"
                        description={
                            can.create
                                ? 'Add your first client to start managing appointments and loyalty rewards.'
                                : 'No clients have been assigned to you yet.'
                        }
                        action={
                            can.create
                                ? {
                                      label: 'Add First Client',
                                      onClick: () => router.get('/clients/create'),
                                  }
                                : undefined
                        }
                    />
                )}
            </div>
        </AppLayout>
    );
}
