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
import { Building2, Plus, Pencil, Trash2, Search, X, Eye } from 'lucide-react';
import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

interface Filters {
    search?: string;
    status?: string;
}

interface Props {
    businesses: PaginatedResponse<Business>;
    filters: Filters;
}

export default function BusinessesIndex({ businesses, filters }: Props) {
    const { t } = useTranslation();
    const [deleteBusiness, setDeleteBusiness] = useState<Business | null>(null);

    const breadcrumbs = [
        { title: t('breadcrumbs.dashboard'), href: '/dashboard' },
        { title: t('breadcrumbs.businesses'), href: '/businesses' },
    ];

    const getStatusLabel = (status: string) => {
        const map: Record<string, string> = {
            active: t('businesses.status_active'),
            inactive: t('businesses.status_inactive'),
            suspended: t('businesses.status_suspended'),
        };
        return map[status] ?? (status.charAt(0).toUpperCase() + status.slice(1));
    };

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
            label: t('businesses.col_name'),
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
            label: t('businesses.col_status'),
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
                        {getStatusLabel(business.status)}
                    </span>
                );
            },
        },
        {
            key: 'users_count',
            label: t('businesses.col_users'),
            render: (business) => (
                <span className="text-sm text-foreground">{business.users_count ?? 0}</span>
            ),
        },
        {
            key: 'employees_count',
            label: t('businesses.col_employees'),
            render: (business) => (
                <span className="text-sm text-foreground">{business.employees_count ?? 0}</span>
            ),
        },
        {
            key: 'services_count',
            label: t('businesses.col_services'),
            render: (business) => (
                <span className="text-sm text-foreground">{business.services_count ?? 0}</span>
            ),
        },
        {
            key: 'actions',
            label: t('common.actions'),
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
            <Head title={t('businesses.title')} />
            <div className="space-y-6 p-4">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight text-foreground">{t('businesses.title')}</h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {t('businesses.manage_subtitle')}
                        </p>
                    </div>
                    <Button onClick={() => router.visit('/businesses/create')}>
                        <Plus className="mr-2 h-4 w-4" />
                        {t('businesses.add_business')}
                    </Button>
                </div>

                {/* Filters */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                    <div className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder={t('businesses.search_placeholder')}
                            value={filters.search || ''}
                            onChange={(e) => handleSearch(e.target.value)}
                            className="pl-9"
                        />
                    </div>

                    <Select value={filters.status || 'all'} onValueChange={handleStatusFilter}>
                        <SelectTrigger className="w-full sm:w-[180px]">
                            <SelectValue placeholder={t('businesses.all_status')} />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">{t('businesses.all_status')}</SelectItem>
                            <SelectItem value="active">{t('businesses.status_active')}</SelectItem>
                            <SelectItem value="inactive">{t('businesses.status_inactive')}</SelectItem>
                            <SelectItem value="suspended">{t('businesses.status_suspended')}</SelectItem>
                        </SelectContent>
                    </Select>

                    {hasActiveFilters && (
                        <Button variant="ghost" size="sm" onClick={clearFilters} className="w-full sm:w-auto">
                            <X className="mr-2 h-4 w-4" />
                            {t('common.clear_filters')}
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
                        title={hasActiveFilters ? t('businesses.empty_title_filtered') : t('businesses.empty_title')}
                        description={
                            hasActiveFilters
                                ? t('businesses.empty_description_filtered')
                                : t('businesses.empty_description')
                        }
                        action={
                            !hasActiveFilters
                                ? {
                                      label: t('businesses.add_business'),
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
                title={t('businesses.delete_title')}
                description={t('businesses.delete_description', { name: deleteBusiness?.name })}
                variant="destructive"
            />
        </AppLayout>
    );
}
