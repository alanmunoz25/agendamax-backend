import { useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { DataTable, type Column } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { ConfirmationModal } from '@/components/confirmation-modal';
import type { Service, ServiceCategory, PaginatedResponse } from '@/types/models';
import { Briefcase, Plus, Pencil, Trash2, Search, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface Filters {
    search?: string;
    category_id?: string;
    parent_category_id?: string;
    is_active?: string;
    sort?: string;
    direction?: 'asc' | 'desc';
}

interface Props {
    services: PaginatedResponse<Service>;
    serviceCategories: ServiceCategory[];
    filters: Filters;
}

export default function ServicesIndex({ services, serviceCategories, filters }: Props) {
    const { t } = useTranslation();
    const [deleteService, setDeleteService] = useState<Service | null>(null);

    const handleSearch = (value: string) => {
        router.get(
            '/services',
            { ...filters, search: value || undefined },
            { preserveState: true, replace: true }
        );
    };

    const handleCategoryFilter = (value: string) => {
        if (value === 'all') {
            const { category_id, parent_category_id, ...rest } = filters;
            router.get('/services', rest, { preserveState: true, replace: true });
        } else if (value.startsWith('parent:')) {
            const { category_id, ...rest } = filters;
            router.get(
                '/services',
                { ...rest, parent_category_id: value.replace('parent:', '') },
                { preserveState: true, replace: true }
            );
        } else {
            const { parent_category_id, ...rest } = filters;
            router.get(
                '/services',
                { ...rest, category_id: value },
                { preserveState: true, replace: true }
            );
        }
    };

    const handleStatusFilter = (value: string) => {
        router.get(
            '/services',
            { ...filters, is_active: value === 'all' ? undefined : value },
            { preserveState: true, replace: true }
        );
    };

    const clearFilters = () => {
        router.get('/services', {}, { preserveState: true, replace: true });
    };

    const handleDelete = () => {
        if (deleteService) {
            router.delete(`/services/${deleteService.id}`, {
                onSuccess: () => setDeleteService(null),
            });
        }
    };

    const getCategoryDisplay = (service: Service) => {
        const sc = service.service_category;
        if (!sc) {
            return service.category || t('common.uncategorized');
        }
        if (sc.parent) {
            return `${sc.parent.name} / ${sc.name}`;
        }
        return sc.name;
    };

    const getCurrentCategoryFilterValue = () => {
        if (filters.category_id) return filters.category_id;
        if (filters.parent_category_id) return `parent:${filters.parent_category_id}`;
        return 'all';
    };

    const columns: Column<Service>[] = [
        {
            key: 'name',
            label: t('services.col_name'),
            sortable: true,
            render: (service) => (
                <div>
                    <div className="font-medium text-foreground">{service.name}</div>
                    {service.description && (
                        <div className="text-sm text-muted-foreground line-clamp-1">
                            {service.description}
                        </div>
                    )}
                </div>
            ),
        },
        {
            key: 'category',
            label: t('services.col_category'),
            render: (service) => (
                <span className="text-sm text-muted-foreground">
                    {getCategoryDisplay(service)}
                </span>
            ),
        },
        {
            key: 'duration',
            label: t('services.col_duration'),
            sortable: true,
            render: (service) => (
                <span className="text-sm text-foreground">
                    {service.duration} {t('services.minutes')}
                </span>
            ),
        },
        {
            key: 'price',
            label: t('services.col_price'),
            sortable: true,
            render: (service) => (
                <span className="text-sm font-medium text-foreground">
                    RD${Number(service.price).toFixed(2)}
                </span>
            ),
        },
        {
            key: 'is_active',
            label: t('services.col_status'),
            render: (service) => (
                <span
                    className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                        service.is_active
                            ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                            : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-400'
                    }`}
                >
                    {service.is_active ? t('services.active') : t('services.inactive')}
                </span>
            ),
        },
        {
            key: 'actions',
            label: t('common.actions'),
            render: (service) => (
                <div className="flex items-center gap-2">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => router.visit(`/services/${service.id}/edit`)}
                    >
                        <Pencil className="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => setDeleteService(service)}
                    >
                        <Trash2 className="h-4 w-4 text-destructive" />
                    </Button>
                </div>
            ),
        },
    ];

    const hasActiveFilters = filters.search || filters.category_id || filters.parent_category_id || filters.is_active;

    return (
        <AppLayout
            title={t('services.title')}
            breadcrumbs={[
                { label: t('breadcrumbs.dashboard'), href: '/dashboard' },
                { label: t('breadcrumbs.services') },
            ]}
        >
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight text-foreground">
                            {t('services.title')}
                        </h1>
                    </div>
                    <Button onClick={() => router.visit('/services/create')}>
                        <Plus className="mr-2 h-4 w-4" />
                        {t('services.new')}
                    </Button>
                </div>

                {/* Filters */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                    <div className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder={t('services.search_placeholder')}
                            value={filters.search || ''}
                            onChange={(e) => handleSearch(e.target.value)}
                            className="pl-9"
                        />
                    </div>

                    <Select
                        value={getCurrentCategoryFilterValue()}
                        onValueChange={handleCategoryFilter}
                    >
                        <SelectTrigger className="w-full sm:w-[220px]">
                            <SelectValue placeholder={t('services.all_categories')} />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">{t('services.all_categories')}</SelectItem>
                            {serviceCategories.map((parent) => (
                                <SelectGroup key={parent.id}>
                                    <SelectLabel className="font-semibold">{parent.name}</SelectLabel>
                                    <SelectItem value={`parent:${parent.id}`}>
                                        {t('common.all')} {parent.name}
                                    </SelectItem>
                                    {parent.children?.map((child) => (
                                        <SelectItem key={child.id} value={String(child.id)}>
                                            {child.name}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.is_active || 'all'}
                        onValueChange={handleStatusFilter}
                    >
                        <SelectTrigger className="w-full sm:w-[180px]">
                            <SelectValue placeholder={t('services.all_status')} />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">{t('services.all_status')}</SelectItem>
                            <SelectItem value="1">{t('services.active')}</SelectItem>
                            <SelectItem value="0">{t('services.inactive')}</SelectItem>
                        </SelectContent>
                    </Select>

                    {hasActiveFilters && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={clearFilters}
                            className="w-full sm:w-auto"
                        >
                            <X className="mr-2 h-4 w-4" />
                            {t('common.clear_filters')}
                        </Button>
                    )}
                </div>

                {/* Table */}
                {services.data.length > 0 ? (
                    <DataTable
                        data={services.data}
                        columns={columns}
                        onRowClick={(service) =>
                            router.visit(`/services/${service.id}`)
                        }
                        pagination={{
                            currentPage: services.current_page,
                            lastPage: services.last_page,
                            onPageChange: (page) =>
                                router.get(
                                    '/services',
                                    { ...filters, page },
                                    { preserveState: true, replace: true }
                                ),
                        }}
                    />
                ) : (
                    <EmptyState
                        icon={Briefcase}
                        title={hasActiveFilters ? t('services.empty_title_filtered') : t('services.empty_title')}
                        description={
                            hasActiveFilters
                                ? t('empty_states.try_filters')
                                : t('services.empty_description')
                        }
                        action={
                            !hasActiveFilters
                                ? {
                                      label: t('services.new'),
                                      onClick: () => router.visit('/services/create'),
                                  }
                                : undefined
                        }
                    />
                )}
            </div>

            {/* Delete Confirmation Modal */}
            <ConfirmationModal
                open={deleteService !== null}
                onClose={() => setDeleteService(null)}
                onConfirm={handleDelete}
                title={t('services.delete_title')}
                description={t('services.delete_description', { name: deleteService?.name })}
                variant="destructive"
            />
        </AppLayout>
    );
}
