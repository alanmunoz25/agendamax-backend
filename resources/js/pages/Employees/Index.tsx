import { useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
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
import type { Employee } from '@/types/models';
import type { PaginatedData } from '@/types/pagination';
import { Plus, Users, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface Props {
    employees: PaginatedData<Employee>;
    filters: {
        search?: string;
        is_active?: string;
        sort?: string;
        direction?: 'asc' | 'desc';
    };
}

export default function EmployeesIndex({ employees, filters }: Props) {
    const { t } = useTranslation();
    const [deleteEmployee, setDeleteEmployee] = useState<Employee | null>(null);

    const handleSearch = (value: string) => {
        router.get(
            '/employees',
            { ...filters, search: value || undefined },
            { preserveState: true, replace: true }
        );
    };

    const handleFilterChange = (key: string, value: string | undefined) => {
        router.get(
            '/employees',
            { ...filters, [key]: value },
            { preserveState: true, replace: true }
        );
    };

    const clearFilters = () => {
        router.get('/employees', {}, { preserveState: true, replace: true });
    };

    const handleDelete = () => {
        if (!deleteEmployee) return;

        router.delete(`/employees/${deleteEmployee.id}`, {
            preserveState: true,
            onSuccess: () => setDeleteEmployee(null),
        });
    };

    const columns: Column<Employee>[] = [
        {
            key: 'name',
            label: t('employees.col_name'),
            sortable: true,
            render: (employee) => (
                <div className="flex items-center gap-3">
                    {employee.photo_url ? (
                        <img
                            src={employee.photo_url}
                            alt={employee.user?.name}
                            className="h-10 w-10 rounded-full object-cover"
                        />
                    ) : (
                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-muted">
                            <Users className="h-5 w-5 text-muted-foreground" />
                        </div>
                    )}
                    <div>
                        <div className="font-medium text-foreground">
                            {employee.user?.name}
                        </div>
                        <div className="text-sm text-muted-foreground">
                            {employee.user?.email}
                        </div>
                    </div>
                </div>
            ),
        },
        {
            key: 'services',
            label: t('employees.col_services'),
            render: (employee) => (
                <div className="flex flex-wrap gap-1">
                    {employee.services && employee.services.length > 0 ? (
                        employee.services.slice(0, 3).map((service) => (
                            <Badge key={service.id} variant="secondary">
                                {service.name}
                            </Badge>
                        ))
                    ) : (
                        <span className="text-sm text-muted-foreground">
                            {t('employees.no_services')}
                        </span>
                    )}
                    {employee.services && employee.services.length > 3 && (
                        <Badge variant="secondary">
                            +{employee.services.length - 3}
                        </Badge>
                    )}
                </div>
            ),
        },
        {
            key: 'is_active',
            label: t('employees.col_status'),
            sortable: true,
            render: (employee) => (
                <Badge variant={employee.is_active ? 'success' : 'secondary'}>
                    {employee.is_active ? t('employees.status_active') : t('employees.status_inactive')}
                </Badge>
            ),
        },
        {
            key: 'actions',
            label: '',
            render: (employee) => (
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={(e) => {
                        e.stopPropagation();
                        setDeleteEmployee(employee);
                    }}
                >
                    {t('common.delete')}
                </Button>
            ),
        },
    ];

    const hasActiveFilters = filters.search || filters.is_active;

    return (
        <AppLayout
            title={t('employees.title')}
            breadcrumbs={[
                { label: t('breadcrumbs.dashboard'), href: '/dashboard' },
                { label: t('breadcrumbs.employees') },
            ]}
        >
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight text-foreground">
                            {t('employees.title')}
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {t('employees.empty_description')}
                        </p>
                    </div>
                    <Button onClick={() => router.visit('/employees/create')}>
                        <Plus className="mr-2 h-4 w-4" />
                        {t('employees.new')}
                    </Button>
                </div>

                {/* Filters */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                    <div className="flex-1">
                        <Input
                            placeholder={t('employees.search_placeholder')}
                            value={filters.search || ''}
                            onChange={(e) => handleSearch(e.target.value)}
                            className="max-w-sm"
                        />
                    </div>

                    <div className="flex items-center gap-2">
                        <Select
                            value={filters.is_active || 'all'}
                            onValueChange={(value) =>
                                handleFilterChange(
                                    'is_active',
                                    value === 'all' ? undefined : value
                                )
                            }
                        >
                            <SelectTrigger className="w-[140px]">
                                <SelectValue placeholder={t('employees.all_status')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{t('employees.all_status')}</SelectItem>
                                <SelectItem value="1">{t('employees.status_active')}</SelectItem>
                                <SelectItem value="0">{t('employees.status_inactive')}</SelectItem>
                            </SelectContent>
                        </Select>

                        {hasActiveFilters && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={clearFilters}
                            >
                                <X className="mr-2 h-4 w-4" />
                                {t('common.clear_filters')}
                            </Button>
                        )}
                    </div>
                </div>

                {/* Table or Empty State */}
                {employees.data.length > 0 ? (
                    <DataTable
                        data={employees.data}
                        columns={columns}
                        onRowClick={(employee) =>
                            router.visit(`/employees/${employee.id}`)
                        }
                        pagination={{
                            currentPage: employees.current_page,
                            lastPage: employees.last_page,
                            from: employees.from ?? 0,
                            to: employees.to ?? 0,
                            total: employees.total,
                            perPage: employees.per_page,
                            onPageChange: (page) =>
                                router.get(
                                    '/employees',
                                    { ...filters, page },
                                    { preserveState: true, replace: true }
                                ),
                        }}
                    />
                ) : (
                    <EmptyState
                        icon={Users}
                        title={
                            hasActiveFilters
                                ? t('employees.empty_title_filtered')
                                : t('employees.empty_title')
                        }
                        description={
                            hasActiveFilters
                                ? t('employees.empty_description_filtered')
                                : t('employees.empty_description')
                        }
                        action={
                            !hasActiveFilters
                                ? {
                                      label: t('employees.add_employee'),
                                      onClick: () =>
                                          router.visit('/employees/create'),
                                  }
                                : undefined
                        }
                    />
                )}
            </div>

            {/* Delete Confirmation Modal */}
            <ConfirmationModal
                open={!!deleteEmployee}
                onOpenChange={(open) => !open && setDeleteEmployee(null)}
                title={t('employees.delete_title')}
                description={
                    <div className="space-y-2">
                        <p>
                            {t('employees.delete_description', { name: deleteEmployee?.user?.name })}
                        </p>
                    </div>
                }
                confirmLabel={t('employees.delete_confirm')}
                cancelLabel={t('common.cancel')}
                onConfirm={handleDelete}
                variant="destructive"
            />
        </AppLayout>
    );
}
