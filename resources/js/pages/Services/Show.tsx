import { useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { ConfirmationModal } from '@/components/confirmation-modal';
import { CommissionRuleFormModal } from '@/components/payroll/commission-rule-form-modal';
import type { Service } from '@/types/models';
import {
    Briefcase,
    Clock,
    DollarSign,
    Tag,
    Pencil,
    Trash2,
    FileText,
    Percent,
    Plus,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { format } from 'date-fns';
import {
    store as storeRule,
    update as updateRule,
} from '@/actions/App/Http/Controllers/Payroll/CommissionRuleController';

interface CommissionRuleForService {
    id: number;
    scope_type: 'per_service' | 'specific';
    type: 'percentage' | 'fixed';
    value: string;
    is_active: boolean;
    employee: {
        id: number;
        name: string | null;
    } | null;
    effective_from: string | null;
    effective_until: string | null;
}

interface EmployeeOption {
    id: number;
    name: string;
}

interface Props {
    service: Service;
    commission_rules: CommissionRuleForService[];
    global_rule_count: number;
    all_employees: EmployeeOption[];
}

export default function ShowService({
    service,
    commission_rules,
    global_rule_count,
    all_employees,
}: Props) {
    const { t } = useTranslation();
    const [showDeleteModal, setShowDeleteModal] = useState(false);
    const [showCommissionModal, setShowCommissionModal] = useState(false);
    const [editRule, setEditRule] = useState<CommissionRuleForService | null>(null);

    const handleDelete = () => {
        router.delete(`/services/${service.id}`, {
            onSuccess: () => router.visit('/services'),
        });
    };

    return (
        <AppLayout
            title={service.name}
            breadcrumbs={[
                { label: t('breadcrumbs.dashboard'), href: '/dashboard' },
                { label: t('breadcrumbs.services'), href: '/services' },
                { label: service.name },
            ]}
        >
            <div className="mx-auto max-w-3xl space-y-6">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight text-foreground">
                            {service.name}
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {t('services.show_subtitle')}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            onClick={() => router.visit(`/services/${service.id}/edit`)}
                        >
                            <Pencil className="mr-2 h-4 w-4" />
                            {t('common.edit')}
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => setShowDeleteModal(true)}
                        >
                            <Trash2 className="mr-2 h-4 w-4" />
                            {t('common.delete')}
                        </Button>
                    </div>
                </div>

                {/* Status Badge */}
                <div>
                    <span
                        className={`inline-flex items-center rounded-full px-3 py-1 text-sm font-medium ${
                            service.is_active
                                ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-400'
                        }`}
                    >
                        {service.is_active ? t('services.active') : t('services.inactive')}
                    </span>
                </div>

                {/* Service Information */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <Briefcase className="h-5 w-5 text-muted-foreground" />
                            <CardTitle>{t('services.info_card_title')}</CardTitle>
                        </div>
                        <CardDescription>
                            {t('services.info_card_desc')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-1">
                                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Tag className="h-4 w-4" />
                                    <span>{t('services.category_label')}</span>
                                </div>
                                <p className="font-medium text-foreground">
                                    {service.service_category?.parent
                                        ? `${service.service_category.parent.name} / ${service.service_category.name}`
                                        : service.service_category?.name || service.category || t('common.uncategorized')}
                                </p>
                            </div>

                            <div className="space-y-1">
                                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <DollarSign className="h-4 w-4" />
                                    <span>{t('services.price_label')}</span>
                                </div>
                                <p className="font-medium text-foreground">
                                    RD${Number(service.price).toFixed(2)}
                                </p>
                            </div>

                            <div className="space-y-1">
                                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Clock className="h-4 w-4" />
                                    <span>{t('services.duration_label')}</span>
                                </div>
                                <p className="font-medium text-foreground">
                                    {service.duration} {t('services.minutes')}
                                </p>
                            </div>
                        </div>

                        {service.description && (
                            <div className="space-y-2 pt-4">
                                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <FileText className="h-4 w-4" />
                                    <span>{t('services.description_label')}</span>
                                </div>
                                <p className="text-sm text-foreground leading-relaxed">
                                    {service.description}
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Metadata */}
                <Card>
                    <CardHeader>
                        <CardTitle>{t('common.metadata')}</CardTitle>
                        <CardDescription>
                            {t('services.metadata_desc')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-muted-foreground">{t('common.created_at')}</span>
                            <span className="font-medium text-foreground">
                                {format(new Date(service.created_at), 'dd/MM/yyyy')}
                            </span>
                        </div>
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-muted-foreground">{t('common.updated_at')}</span>
                            <span className="font-medium text-foreground">
                                {format(new Date(service.updated_at), 'dd/MM/yyyy')}
                            </span>
                        </div>
                    </CardContent>
                </Card>

                {/* Commission Rules — Mejora #8 */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Percent className="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <CardTitle>{t('services.commissions_card_title')}</CardTitle>
                                    <CardDescription className="mt-1">
                                        {t('services.commissions_card_desc')}
                                    </CardDescription>
                                </div>
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    setEditRule(null);
                                    setShowCommissionModal(true);
                                }}
                            >
                                <Plus className="mr-2 h-4 w-4" />
                                {t('services.create_commission_btn')}
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {commission_rules.length === 0 ? (
                            <div className="py-12 text-center">
                                <Percent className="mx-auto mb-4 h-12 w-12 text-muted-foreground/50" />
                                <p className="mb-1 font-medium text-foreground">
                                    {t('services.no_commission_rules')}
                                </p>
                                <p className="mb-4 text-sm text-muted-foreground">
                                    {t('services.no_commission_rules_desc')}
                                </p>
                                <Button
                                    variant="outline"
                                    onClick={() => {
                                        setEditRule(null);
                                        setShowCommissionModal(true);
                                    }}
                                >
                                    <Plus className="mr-2 h-4 w-4" />
                                    {t('services.create_commission_btn')}
                                </Button>
                            </div>
                        ) : (
                            <div className="space-y-4">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>{t('services.commission_col_employee')}</TableHead>
                                            <TableHead>{t('services.commission_col_scope')}</TableHead>
                                            <TableHead>{t('services.commission_col_value')}</TableHead>
                                            <TableHead>{t('services.commission_col_status')}</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {commission_rules.map((rule) => (
                                            <TableRow
                                                key={rule.id}
                                                className="cursor-pointer hover:bg-muted/50"
                                                onClick={() => {
                                                    setEditRule(rule);
                                                    setShowCommissionModal(true);
                                                }}
                                            >
                                                <TableCell>
                                                    {rule.employee ? (
                                                        <span>{rule.employee.name}</span>
                                                    ) : (
                                                        <span className="text-muted-foreground italic">
                                                            {t('services.commission_all_employees')}
                                                        </span>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    {rule.scope_type === 'per_service'
                                                        ? t('services.commission_scope_per_service')
                                                        : t('services.commission_scope_specific')}
                                                </TableCell>
                                                <TableCell className="font-medium">
                                                    {rule.type === 'percentage'
                                                        ? `${Number(rule.value).toFixed(0)}%`
                                                        : `RD$${Number(rule.value).toFixed(2)} fijo`}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge
                                                        className={
                                                            rule.is_active
                                                                ? 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-300'
                                                                : 'bg-secondary text-secondary-foreground'
                                                        }
                                                    >
                                                        {rule.is_active
                                                            ? t('services.commission_active')
                                                            : t('services.commission_inactive')}
                                                    </Badge>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>

                                {global_rule_count > 0 && (
                                    <div className="flex items-center gap-2 rounded-md bg-muted/50 px-4 py-2.5 text-sm text-muted-foreground">
                                        <span className="text-base">ⓘ</span>
                                        <span>
                                            {t('services.commission_global_note', {
                                                count: global_rule_count,
                                            })}
                                        </span>
                                        <button
                                            type="button"
                                            className="ml-1 text-primary underline-offset-2 hover:underline"
                                            onClick={() =>
                                                router.visit('/payroll/commission-rules')
                                            }
                                        >
                                            {t('services.commission_global_link')} →
                                        </button>
                                    </div>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Delete Confirmation Modal */}
            <ConfirmationModal
                open={showDeleteModal}
                onOpenChange={setShowDeleteModal}
                title={t('services.delete_title')}
                description={t('services.delete_description', { name: service.name })}
                confirmLabel={t('common.yes_delete')}
                cancelLabel={t('common.cancel')}
                onConfirm={handleDelete}
                variant="destructive"
            />

            {/* Commission Rule Modal — Mejora #8 */}
            <CommissionRuleFormModal
                open={showCommissionModal}
                onOpenChange={(open) => {
                    setShowCommissionModal(open);
                    if (!open) setEditRule(null);
                }}
                rule={
                    editRule
                        ? {
                              ...editRule,
                              priority: 0,
                              service: { id: service.id, name: service.name },
                          }
                        : null
                }
                employees={all_employees}
                services={[{ id: service.id, name: service.name }]}
                storeUrl={storeRule.url()}
                updateUrl={editRule ? updateRule.url(editRule) : undefined}
            />
        </AppLayout>
    );
}
