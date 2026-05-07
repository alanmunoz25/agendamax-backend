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
import {
    Card,
    CardContent,
} from '@/components/ui/card';
import { ConfirmationModal } from '@/components/confirmation-modal';
import { EmptyState } from '@/components/empty-state';
import type { Promotion, PaginatedResponse } from '@/types/models';
import {
    Megaphone,
    Plus,
    Pencil,
    Trash2,
    Search,
    X,
    ExternalLink,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { format } from 'date-fns';

interface Filters {
    search?: string;
    is_active?: string;
}

interface Props {
    promotions: PaginatedResponse<Promotion>;
    filters: Filters;
}

export default function PromotionsIndex({ promotions, filters }: Props) {
    const { t } = useTranslation();
    const [deletePromotion, setDeletePromotion] = useState<Promotion | null>(null);

    const getStatusBadge = (promotion: Promotion) => {
        const isExpired = promotion.expires_at && new Date(promotion.expires_at) < new Date(new Date().toDateString());

        if (isExpired) {
            return (
                <span className="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800 dark:bg-red-900/30 dark:text-red-400">
                    {t('promotions.status_expired')}
                </span>
            );
        }

        if (!promotion.is_active) {
            return (
                <span className="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800 dark:bg-gray-800 dark:text-gray-400">
                    {t('promotions.status_inactive')}
                </span>
            );
        }

        return (
            <span className="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400">
                {t('promotions.status_active')}
            </span>
        );
    };

    const handleSearch = (value: string) => {
        router.get(
            '/promotions',
            { ...filters, search: value || undefined },
            { preserveState: true, replace: true },
        );
    };

    const handleStatusFilter = (value: string) => {
        router.get(
            '/promotions',
            { ...filters, is_active: value === 'all' ? undefined : value },
            { preserveState: true, replace: true },
        );
    };

    const clearFilters = () => {
        router.get('/promotions', {}, { preserveState: true, replace: true });
    };

    const handleDelete = () => {
        if (deletePromotion) {
            router.delete(`/promotions/${deletePromotion.id}`, {
                onSuccess: () => setDeletePromotion(null),
                onError: () => setDeletePromotion(null),
            });
        }
    };

    const hasActiveFilters = filters.search || filters.is_active;

    return (
        <AppLayout
            title={t('promotions.title')}
            breadcrumbs={[
                { label: t('breadcrumbs.dashboard'), href: '/dashboard' },
                { label: t('breadcrumbs.promotions'), href: '/promotions' },
            ]}
        >
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight text-foreground">{t('promotions.title')}</h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {t('promotions.subtitle')}
                        </p>
                    </div>
                    <Button onClick={() => router.visit('/promotions/create')}>
                        <Plus className="mr-2 h-4 w-4" />
                        {t('promotions.add_promotion')}
                    </Button>
                </div>

                {/* Filters */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                    <div className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder={t('promotions.search_placeholder')}
                            value={filters.search || ''}
                            onChange={(e) => handleSearch(e.target.value)}
                            className="pl-9"
                        />
                    </div>

                    <Select value={filters.is_active || 'all'} onValueChange={handleStatusFilter}>
                        <SelectTrigger className="w-full sm:w-[180px]">
                            <SelectValue placeholder={t('promotions.all_status')} />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">{t('promotions.all_status')}</SelectItem>
                            <SelectItem value="1">{t('common.active')}</SelectItem>
                            <SelectItem value="0">{t('common.inactive')}</SelectItem>
                        </SelectContent>
                    </Select>

                    {hasActiveFilters && (
                        <Button variant="ghost" size="sm" onClick={clearFilters} className="w-full sm:w-auto">
                            <X className="mr-2 h-4 w-4" />
                            {t('common.clear_filters')}
                        </Button>
                    )}
                </div>

                {/* Promotions Grid */}
                {promotions.data.length > 0 ? (
                    <div className="space-y-6">
                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                            {promotions.data.map((promotion) => (
                                <Card key={promotion.id} className="overflow-hidden">
                                    {/* Image */}
                                    <div className="aspect-[16/9] overflow-hidden bg-muted">
                                        {promotion.image_url ? (
                                            <img
                                                src={promotion.image_url}
                                                alt={promotion.title}
                                                className="h-full w-full object-cover"
                                            />
                                        ) : (
                                            <div className="flex h-full items-center justify-center">
                                                <Megaphone className="h-12 w-12 text-muted-foreground/50" />
                                            </div>
                                        )}
                                    </div>

                                    <CardContent className="p-4">
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="min-w-0 flex-1">
                                                <h3 className="truncate text-sm font-semibold text-foreground">
                                                    {promotion.title}
                                                </h3>
                                                <div className="mt-2 flex flex-wrap items-center gap-2">
                                                    {getStatusBadge(promotion)}
                                                    {promotion.expires_at && (
                                                        <span className="text-xs text-muted-foreground">
                                                            {t('promotions.expires_label', {
                                                                date: format(new Date(promotion.expires_at), 'dd/MM/yyyy'),
                                                            })}
                                                        </span>
                                                    )}
                                                </div>
                                                {promotion.url && (
                                                    <a
                                                        href={promotion.url}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="mt-2 inline-flex items-center gap-1 truncate text-xs text-blue-600 hover:underline dark:text-blue-400"
                                                    >
                                                        <ExternalLink className="h-3 w-3 shrink-0" />
                                                        <span className="truncate">{promotion.url}</span>
                                                    </a>
                                                )}
                                            </div>
                                        </div>

                                        <div className="mt-4 flex items-center justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => router.visit(`/promotions/${promotion.id}/edit`)}
                                            >
                                                <Pencil className="h-4 w-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => setDeletePromotion(promotion)}
                                            >
                                                <Trash2 className="h-4 w-4 text-destructive" />
                                            </Button>
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>

                        {/* Pagination */}
                        {promotions.last_page > 1 && (
                            <div className="flex items-center justify-between">
                                <p className="text-sm text-muted-foreground">
                                    {t('promotions.showing_range', {
                                        from: promotions.from,
                                        to: promotions.to,
                                        total: promotions.total,
                                    })}
                                </p>
                                <div className="flex gap-2">
                                    {Array.from({ length: promotions.last_page }, (_, i) => i + 1).map((page) => (
                                        <Button
                                            key={page}
                                            variant={page === promotions.current_page ? 'default' : 'outline'}
                                            size="sm"
                                            onClick={() =>
                                                router.get(
                                                    '/promotions',
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
                        icon={Megaphone}
                        title={hasActiveFilters ? t('promotions.empty_title_filtered') : t('promotions.empty_title')}
                        description={
                            hasActiveFilters
                                ? t('promotions.empty_description_filtered')
                                : t('promotions.empty_description')
                        }
                        action={
                            !hasActiveFilters
                                ? {
                                      label: t('promotions.add_promotion'),
                                      onClick: () => router.visit('/promotions/create'),
                                  }
                                : undefined
                        }
                    />
                )}
            </div>

            {/* Delete Confirmation Modal */}
            {deletePromotion && (
                <ConfirmationModal
                    open={true}
                    onOpenChange={(open) => { if (!open) setDeletePromotion(null); }}
                    onConfirm={handleDelete}
                    title={t('promotions.delete_title')}
                    description={t('promotions.delete_description', { name: deletePromotion.title })}
                    confirmLabel={t('common.delete')}
                    variant="destructive"
                />
            )}
        </AppLayout>
    );
}
