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
import type { User, Business, PaginatedResponse } from '@/types/models';
import { type SharedData } from '@/types';
import { Users as UsersIcon, Pencil, Search, X, UserPlus } from 'lucide-react';
import { Head, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

interface Filters {
    search?: string;
    role?: string;
    business_id?: string;
}

interface Props {
    users: PaginatedResponse<User>;
    businesses: Pick<Business, 'id' | 'name'>[];
    filters: Filters;
}

const roleStyles: Record<string, string> = {
    super_admin: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
    business_admin: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
    employee: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    client: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-400',
};

export default function UsersIndex({ users, businesses, filters }: Props) {
    const { t } = useTranslation();
    const { permissions } = usePage<SharedData>().props;

    const roleLabels: Record<string, string> = {
        super_admin: t('users.role_super_admin'),
        business_admin: t('users.role_business_admin'),
        employee: t('users.role_employee'),
        client: t('users.role_client'),
    };

    const breadcrumbs = [
        { title: t('breadcrumbs.dashboard'), href: '/dashboard' },
        { title: t('breadcrumbs.users'), href: '/users' },
    ];

    const handleSearch = (value: string) => {
        router.get('/users', { ...filters, search: value || undefined }, { preserveState: true, replace: true });
    };

    const handleRoleFilter = (value: string) => {
        router.get(
            '/users',
            { ...filters, role: value === 'all' ? undefined : value },
            { preserveState: true, replace: true },
        );
    };

    const handleBusinessFilter = (value: string) => {
        router.get(
            '/users',
            { ...filters, business_id: value === 'all' ? undefined : value },
            { preserveState: true, replace: true },
        );
    };

    const clearFilters = () => {
        router.get('/users', {}, { preserveState: true, replace: true });
    };

    const columns: Column<User>[] = [
        {
            key: 'name',
            label: t('users.col_user'),
            render: (user) => (
                <div>
                    <div className="font-medium text-foreground">{user.name}</div>
                    <div className="text-sm text-muted-foreground">{user.email}</div>
                </div>
            ),
        },
        {
            key: 'role',
            label: t('users.col_role'),
            render: (user) => (
                <span
                    className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${roleStyles[user.role] || roleStyles.client}`}
                >
                    {roleLabels[user.role] || user.role}
                </span>
            ),
        },
        {
            key: 'business',
            label: t('users.col_business'),
            render: (user) => (
                <span className="text-sm text-muted-foreground">{user.business?.name || t('common.none')}</span>
            ),
        },
        {
            key: 'actions',
            label: t('common.actions'),
            render: (user) => (
                <div className="flex items-center gap-2">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={(e) => {
                            e.stopPropagation();
                            router.visit(`/users/${user.id}/edit`);
                        }}
                    >
                        <Pencil className="h-4 w-4" />
                    </Button>
                </div>
            ),
        },
    ];

    const hasActiveFilters = filters.search || filters.role || filters.business_id;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('users.title')} />
            <div className="space-y-6 p-4">
                {/* Header */}
                <div>
                    <h1 className="text-3xl font-bold tracking-tight text-foreground">{t('users.title')}</h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        {permissions.is_super_admin
                            ? t('users.manage_platform')
                            : t('users.manage_business')}
                    </p>
                </div>

                {/* Actions */}
                <div className="flex justify-end">
                    <Button onClick={() => router.visit('/users/create')}>
                        <UserPlus className="mr-2 h-4 w-4" />
                        {t('users.create_user')}
                    </Button>
                </div>

                {/* Filters */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                    <div className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder={t('users.search_placeholder')}
                            value={filters.search || ''}
                            onChange={(e) => handleSearch(e.target.value)}
                            className="pl-9"
                        />
                    </div>

                    <Select value={filters.role || 'all'} onValueChange={handleRoleFilter}>
                        <SelectTrigger className="w-full sm:w-[180px]">
                            <SelectValue placeholder={t('users.all_roles')} />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">{t('users.all_roles')}</SelectItem>
                            <SelectItem value="super_admin">{t('users.role_super_admin')}</SelectItem>
                            <SelectItem value="business_admin">{t('users.role_business_admin')}</SelectItem>
                            <SelectItem value="employee">{t('users.role_employee')}</SelectItem>
                            <SelectItem value="client">{t('users.role_client')}</SelectItem>
                        </SelectContent>
                    </Select>

                    {permissions.is_super_admin && businesses.length > 0 && (
                        <Select value={filters.business_id || 'all'} onValueChange={handleBusinessFilter}>
                            <SelectTrigger className="w-full sm:w-[200px]">
                                <SelectValue placeholder={t('users.all_businesses')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{t('users.all_businesses')}</SelectItem>
                                {businesses.map((b) => (
                                    <SelectItem key={b.id} value={String(b.id)}>
                                        {b.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}

                    {hasActiveFilters && (
                        <Button variant="ghost" size="sm" onClick={clearFilters} className="w-full sm:w-auto">
                            <X className="mr-2 h-4 w-4" />
                            {t('common.clear_filters')}
                        </Button>
                    )}
                </div>

                {/* Table */}
                {users.data.length > 0 ? (
                    <DataTable
                        data={users.data}
                        columns={columns}
                        pagination={{
                            currentPage: users.current_page,
                            lastPage: users.last_page,
                            onPageChange: (page) =>
                                router.get('/users', { ...filters, page }, { preserveState: true, replace: true }),
                        }}
                    />
                ) : (
                    <EmptyState
                        icon={UsersIcon}
                        title={hasActiveFilters ? t('users.empty_title_filtered') : t('users.empty_title')}
                        description={
                            hasActiveFilters
                                ? t('users.empty_description_filtered')
                                : t('users.empty_description')
                        }
                    />
                )}
            </div>
        </AppLayout>
    );
}
