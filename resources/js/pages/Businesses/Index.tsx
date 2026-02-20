import { useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { DataTable, type Column } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { ConfirmationModal } from '@/components/confirmation-modal';
import type { Business, PaginatedResponse } from '@/types/models';
import { type BreadcrumbItem } from '@/types';
import { Building2, Plus, Pencil, Trash2, Search, X, Eye } from 'lucide-react';
import { Head } from '@inertiajs/react';

interface Filters {
    search?: string;
    status?: string;
}

interface Props {
    businesses: PaginatedResponse<Business>;
    filters: Filters;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Businesses', href: '/businesses' },
];

export default function BusinessesIndex({ businesses, filters }: Props) {
    const [deleteBusiness, setDeleteBusiness] = useState<Business | null>(null);

    const handleSearch = (value: string) => {
        router.get(
            '/businesses',
            { ...filters, search: value || undefined },
            { preserveState: true, replace: true },
        );
    };

    const handleStatusFilter = (value: string) => {
        router.get(
            '/businesses',
            { ...filters, status: value === 'all' ? undefined : value },
            { preserveState: true, replace: true },
        );
    };

    const clearFilters = () => {
        router.get('/businesses', {}, { preserveState: true, replace: true });
    };

    const handleDelete = () => {
        if (deleteBusiness) {
            router.delete(`/businesses/${deleteBusiness.id}`, {
                onSuccess: () => setDeleteBusiness(null),
            });
        }
    };

    const columns: Column<Business>[] = [
        {
            key: 'name',
            label: 'Business',
            render: (business) => (
                <div>
                    <div className="font-medium text-foreground">{business.name}</div>
                    {business.email && (
                        <div className="text-sm text-muted-foreground">{business.email}</div>
                    )}
                </div>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            render: (business) => {
                const statusStyles: Record<string, string> = {
                    active: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
                    inactive: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-400',
                    suspended: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
                };
                return (
                    <span
                        className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${statusStyles[business.status] || statusStyles.inactive}`}
                    >
                        {business.status.charAt(0).toUpperCase() + business.status.slice(1)}
                    </span>
                );
            },
        },
        {
            key: 'users_count',
            label: 'Users',
            render: (business) => (
                <span className="text-sm text-foreground">{business.users_count ?? 0}</span>
            ),
        },
        {
            key: 'employees_count',
            label: 'Employees',
            render: (business) => (
                <span className="text-sm text-foreground">{business.employees_count ?? 0}</span>
            ),
        },
        {
            key: 'services_count',
            label: 'Services',
            render: (business) => (
                <span className="text-sm text-foreground">{business.services_count ?? 0}</span>
            ),
        },
        {
            key: 'actions',
            label: 'Actions',
            render: (business) => (
                <div className="flex items-center gap-2">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={(e) => {
                            e.stopPropagation();
                            router.visit(`/businesses/${business.id}`);
                        }}
                    >
                        <Eye className="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={(e) => {
                            e.stopPropagation();
                            router.visit(`/businesses/${business.id}/edit`);
                        }}
                    >
                        <Pencil className="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={(e) => {
                            e.stopPropagation();
                            setDeleteBusiness(business);
                        }}
                    >
                        <Trash2 className="h-4 w-4 text-destructive" />
                    </Button>
                </div>
            ),
        },
    ];

    const hasActiveFilters = filters.search || filters.status;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Businesses" />
            <div className="space-y-6 p-4">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight text-foreground">Businesses</h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Manage all businesses on the platform
                        </p>
                    </div>
                    <Button onClick={() => router.visit('/businesses/create')}>
                        <Plus className="mr-2 h-4 w-4" />
                        Add Business
                    </Button>
                </div>

                {/* Filters */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                    <div className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder="Search businesses..."
                            value={filters.search || ''}
                            onChange={(e) => handleSearch(e.target.value)}
                            className="pl-9"
                        />
                    </div>

                    <Select value={filters.status || 'all'} onValueChange={handleStatusFilter}>
                        <SelectTrigger className="w-full sm:w-[180px]">
                            <SelectValue placeholder="All Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Status</SelectItem>
                            <SelectItem value="active">Active</SelectItem>
                            <SelectItem value="inactive">Inactive</SelectItem>
                            <SelectItem value="suspended">Suspended</SelectItem>
                        </SelectContent>
                    </Select>

                    {hasActiveFilters && (
                        <Button variant="ghost" size="sm" onClick={clearFilters} className="w-full sm:w-auto">
                            <X className="mr-2 h-4 w-4" />
                            Clear
                        </Button>
                    )}
                </div>

                {/* Table */}
                {businesses.data.length > 0 ? (
                    <DataTable
                        data={businesses.data}
                        columns={columns}
                        onRowClick={(business) => router.visit(`/businesses/${business.id}`)}
                        pagination={{
                            currentPage: businesses.current_page,
                            lastPage: businesses.last_page,
                            onPageChange: (page) =>
                                router.get('/businesses', { ...filters, page }, { preserveState: true, replace: true }),
                        }}
                    />
                ) : (
                    <EmptyState
                        icon={Building2}
                        title="No businesses found"
                        description={
                            hasActiveFilters
                                ? 'No businesses match your current filters. Try adjusting your search criteria.'
                                : 'No businesses have been created yet. Get started by adding the first business.'
                        }
                        action={
                            !hasActiveFilters
                                ? {
                                      label: 'Add Business',
                                      onClick: () => router.visit('/businesses/create'),
                                  }
                                : undefined
                        }
                    />
                )}
            </div>

            <ConfirmationModal
                open={deleteBusiness !== null}
                onClose={() => setDeleteBusiness(null)}
                onConfirm={handleDelete}
                title="Delete Business"
                description={`Are you sure you want to delete "${deleteBusiness?.name}"? This will remove all associated data. This action cannot be undone.`}
                variant="destructive"
            />
        </AppLayout>
    );
}
