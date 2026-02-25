import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
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
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { ConfirmationModal } from '@/components/confirmation-modal';
import { EmptyState } from '@/components/empty-state';
import type { ServiceCategory, PaginatedResponse } from '@/types/models';
import {
    FolderTree,
    Plus,
    Pencil,
    Trash2,
    Search,
    X,
    ChevronDown,
    ChevronRight,
} from 'lucide-react';

interface Filters {
    search?: string;
    is_active?: string;
}

interface Props {
    categories: PaginatedResponse<ServiceCategory>;
    filters: Filters;
}

export default function ServiceCategoriesIndex({ categories, filters }: Props) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const [deleteCategory, setDeleteCategory] = useState<ServiceCategory | null>(null);
    const [expandedCategories, setExpandedCategories] = useState<Set<number>>(new Set());

    const toggleExpanded = (id: number) => {
        setExpandedCategories((prev) => {
            const next = new Set(prev);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }
            return next;
        });
    };

    const handleSearch = (value: string) => {
        router.get(
            '/service-categories',
            { ...filters, search: value || undefined },
            { preserveState: true, replace: true },
        );
    };

    const handleStatusFilter = (value: string) => {
        router.get(
            '/service-categories',
            { ...filters, is_active: value === 'all' ? undefined : value },
            { preserveState: true, replace: true },
        );
    };

    const clearFilters = () => {
        router.get('/service-categories', {}, { preserveState: true, replace: true });
    };

    const handleDelete = () => {
        if (deleteCategory) {
            router.delete(`/service-categories/${deleteCategory.id}`, {
                onSuccess: () => setDeleteCategory(null),
                onError: () => setDeleteCategory(null),
            });
        }
    };

    const canDelete = (category: ServiceCategory): boolean => {
        const ownServices = category.services_count ?? 0;
        const childrenServices = (category.children ?? []).reduce(
            (sum, child) => sum + (child.services_count ?? 0),
            0,
        );
        return ownServices + childrenServices === 0;
    };

    const getTotalServices = (category: ServiceCategory): number => {
        const ownServices = category.services_count ?? 0;
        const childrenServices = (category.children ?? []).reduce(
            (sum, child) => sum + (child.services_count ?? 0),
            0,
        );
        return ownServices + childrenServices;
    };

    const hasActiveFilters = filters.search || filters.is_active;

    return (
        <AppLayout
            title="Categories"
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Categories', href: '/service-categories' },
            ]}
        >
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight text-foreground">Categories</h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Manage your service categories and subcategories
                        </p>
                    </div>
                    <Button onClick={() => router.visit('/service-categories/create')}>
                        <Plus className="mr-2 h-4 w-4" />
                        Add Category
                    </Button>
                </div>

                {/* Delete error alert */}
                {errors.delete && (
                    <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-4 text-sm text-destructive">
                        {errors.delete}
                    </div>
                )}

                {/* Filters */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                    <div className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder="Search categories..."
                            value={filters.search || ''}
                            onChange={(e) => handleSearch(e.target.value)}
                            className="pl-9"
                        />
                    </div>

                    <Select value={filters.is_active || 'all'} onValueChange={handleStatusFilter}>
                        <SelectTrigger className="w-full sm:w-[180px]">
                            <SelectValue placeholder="All Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Status</SelectItem>
                            <SelectItem value="1">Active</SelectItem>
                            <SelectItem value="0">Inactive</SelectItem>
                        </SelectContent>
                    </Select>

                    {hasActiveFilters && (
                        <Button variant="ghost" size="sm" onClick={clearFilters} className="w-full sm:w-auto">
                            <X className="mr-2 h-4 w-4" />
                            Clear
                        </Button>
                    )}
                </div>

                {/* Categories List */}
                {categories.data.length > 0 ? (
                    <div className="space-y-4">
                        {categories.data.map((category) => (
                            <Card key={category.id}>
                                <CardHeader className="pb-3">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-3">
                                            {(category.children_count ?? 0) > 0 && (
                                                <button
                                                    type="button"
                                                    onClick={() => toggleExpanded(category.id)}
                                                    className="rounded p-0.5 hover:bg-muted"
                                                >
                                                    {expandedCategories.has(category.id) ? (
                                                        <ChevronDown className="h-4 w-4 text-muted-foreground" />
                                                    ) : (
                                                        <ChevronRight className="h-4 w-4 text-muted-foreground" />
                                                    )}
                                                </button>
                                            )}
                                            <div>
                                                <CardTitle className="text-base">{category.name}</CardTitle>
                                                {category.description && (
                                                    <p className="mt-1 text-sm text-muted-foreground line-clamp-1">
                                                        {category.description}
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                                <span>{getTotalServices(category)} services</span>
                                                {(category.children_count ?? 0) > 0 && (
                                                    <>
                                                        <span className="text-border">|</span>
                                                        <span>{category.children_count} subcategories</span>
                                                    </>
                                                )}
                                            </div>
                                            <span
                                                className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                                    category.is_active
                                                        ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                                        : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-400'
                                                }`}
                                            >
                                                {category.is_active ? 'Active' : 'Inactive'}
                                            </span>
                                            <div className="flex items-center gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => router.visit(`/service-categories/${category.id}/edit`)}
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => setDeleteCategory(category)}
                                                >
                                                    <Trash2 className="h-4 w-4 text-destructive" />
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                </CardHeader>

                                {/* Expandable children */}
                                {expandedCategories.has(category.id) && category.children && category.children.length > 0 && (
                                    <CardContent className="pt-0">
                                        <div className="ml-7 space-y-2 border-l-2 border-muted pl-4">
                                            {category.children.map((child) => (
                                                <div
                                                    key={child.id}
                                                    className="flex items-center justify-between rounded-lg bg-muted/50 px-4 py-3"
                                                >
                                                    <div>
                                                        <p className="text-sm font-medium text-foreground">{child.name}</p>
                                                        {child.description && (
                                                            <p className="text-xs text-muted-foreground line-clamp-1">
                                                                {child.description}
                                                            </p>
                                                        )}
                                                    </div>
                                                    <div className="flex items-center gap-3">
                                                        <span className="text-xs text-muted-foreground">
                                                            {child.services_count ?? 0} services
                                                        </span>
                                                        <span
                                                            className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                                                                child.is_active
                                                                    ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                                                    : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-400'
                                                            }`}
                                                        >
                                                            {child.is_active ? 'Active' : 'Inactive'}
                                                        </span>
                                                        <div className="flex items-center gap-1">
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={() =>
                                                                    router.visit(`/service-categories/${child.id}/edit`)
                                                                }
                                                            >
                                                                <Pencil className="h-3.5 w-3.5" />
                                                            </Button>
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={() => setDeleteCategory(child)}
                                                            >
                                                                <Trash2 className="h-3.5 w-3.5 text-destructive" />
                                                            </Button>
                                                        </div>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </CardContent>
                                )}
                            </Card>
                        ))}

                        {/* Pagination */}
                        {categories.last_page > 1 && (
                            <div className="flex items-center justify-between">
                                <p className="text-sm text-muted-foreground">
                                    Showing {categories.from} to {categories.to} of {categories.total} categories
                                </p>
                                <div className="flex gap-2">
                                    {Array.from({ length: categories.last_page }, (_, i) => i + 1).map((page) => (
                                        <Button
                                            key={page}
                                            variant={page === categories.current_page ? 'default' : 'outline'}
                                            size="sm"
                                            onClick={() =>
                                                router.get(
                                                    '/service-categories',
                                                    { ...filters, page },
                                                    { preserveState: true, replace: true },
                                                )
                                            }
                                        >
                                            {page}
                                        </Button>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                ) : (
                    <EmptyState
                        icon={FolderTree}
                        title="No categories found"
                        description={
                            hasActiveFilters
                                ? 'No categories match your current filters. Try adjusting your search criteria.'
                                : "You haven't created any categories yet. Get started by adding your first category."
                        }
                        action={
                            !hasActiveFilters
                                ? {
                                      label: 'Add Category',
                                      onClick: () => router.visit('/service-categories/create'),
                                  }
                                : undefined
                        }
                    />
                )}
            </div>

            {/* Delete Confirmation / Warning Modal */}
            {deleteCategory && (
                canDelete(deleteCategory) ? (
                    <ConfirmationModal
                        open={true}
                        onOpenChange={(open) => { if (!open) setDeleteCategory(null); }}
                        onConfirm={handleDelete}
                        title="Delete Category"
                        description={`Are you sure you want to delete "${deleteCategory.name}"?${(deleteCategory.children_count ?? 0) > 0 ? ` This will also delete ${deleteCategory.children_count} subcategories.` : ''} This action cannot be undone.`}
                        confirmLabel="Delete"
                        variant="destructive"
                    />
                ) : (
                    <ConfirmationModal
                        open={true}
                        onOpenChange={(open) => { if (!open) setDeleteCategory(null); }}
                        onConfirm={() => setDeleteCategory(null)}
                        title="Cannot Delete Category"
                        description={
                            <div className="space-y-2">
                                <p>
                                    This category has <strong>{getTotalServices(deleteCategory)} service(s)</strong> assigned.
                                </p>
                                <p>Please reassign or delete those services before removing this category.</p>
                            </div>
                        }
                        confirmLabel="Close"
                        variant="default"
                    />
                )
            )}
        </AppLayout>
    );
}
