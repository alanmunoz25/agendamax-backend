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
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Users as UsersIcon, Pencil, Search, X, UserPlus } from 'lucide-react';
import { Head, usePage } from '@inertiajs/react';

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

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Users', href: '/users' },
];

const roleLabels: Record<string, string> = {
    super_admin: 'Super Admin',
    business_admin: 'Business Admin',
    employee: 'Employee',
    client: 'Client',
};

const roleStyles: Record<string, string> = {
    super_admin: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
    business_admin: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
    employee: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    client: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-400',
};

export default function UsersIndex({ users, businesses, filters }: Props) {
    const { permissions } = usePage<SharedData>().props;

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
            label: 'User',
            render: (user) => (
                <div>
                    <div className="font-medium text-foreground">{user.name}</div>
                    <div className="text-sm text-muted-foreground">{user.email}</div>
                </div>
            ),
        },
        {
            key: 'role',
            label: 'Role',
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
            label: 'Business',
            render: (user) => (
                <span className="text-sm text-muted-foreground">{user.business?.name || 'None'}</span>
            ),
        },
        {
            key: 'actions',
            label: 'Actions',
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
            <Head title="Users" />
            <div className="space-y-6 p-4">
                {/* Header */}
                <div>
                    <h1 className="text-3xl font-bold tracking-tight text-foreground">Users</h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        {permissions.is_super_admin
                            ? 'Manage all users across the platform'
                            : 'Manage users in your business'}
                    </p>
                </div>

                {/* Actions */}
                <div className="flex justify-end">
                    <Button onClick={() => router.visit('/users/create')}>
                        <UserPlus className="mr-2 h-4 w-4" />
                        Create User
                    </Button>
                </div>

                {/* Filters */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                    <div className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder="Search users..."
                            value={filters.search || ''}
                            onChange={(e) => handleSearch(e.target.value)}
                            className="pl-9"
                        />
                    </div>

                    <Select value={filters.role || 'all'} onValueChange={handleRoleFilter}>
                        <SelectTrigger className="w-full sm:w-[180px]">
                            <SelectValue placeholder="All Roles" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Roles</SelectItem>
                            <SelectItem value="super_admin">Super Admin</SelectItem>
                            <SelectItem value="business_admin">Business Admin</SelectItem>
                            <SelectItem value="employee">Employee</SelectItem>
                            <SelectItem value="client">Client</SelectItem>
                        </SelectContent>
                    </Select>

                    {permissions.is_super_admin && businesses.length > 0 && (
                        <Select value={filters.business_id || 'all'} onValueChange={handleBusinessFilter}>
                            <SelectTrigger className="w-full sm:w-[200px]">
                                <SelectValue placeholder="All Businesses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Businesses</SelectItem>
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
                            Clear
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
                        title="No users found"
                        description={
                            hasActiveFilters
                                ? 'No users match your current filters. Try adjusting your search criteria.'
                                : 'No users have been created yet.'
                        }
                    />
                )}
            </div>
        </AppLayout>
    );
}
