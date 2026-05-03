import { CommissionRuleFormModal } from '@/components/payroll/commission-rule-form-modal';
import { ScopeTypeBadge } from '@/components/payroll/scope-type-badge';
import { DataTable, type Column } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import {
    destroy as destroyRule,
    store as storeRule,
    update as updateRule,
} from '@/actions/App/Http/Controllers/Payroll/CommissionRuleController';
import { Head, router } from '@inertiajs/react';
import { ChevronDown, Info, Percent, Plus } from 'lucide-react';
import { useState } from 'react';

type ScopeType = 'global' | 'per_service' | 'per_employee' | 'specific';

interface CommissionRule {
    id: number;
    scope_type: ScopeType;
    employee: { id: number; name: string | null } | null;
    service: { id: number; name: string } | null;
    type: 'percentage' | 'fixed';
    value: string;
    priority: number;
    is_active: boolean;
    effective_from: string | null;
    effective_until: string | null;
}

interface EmployeeOption {
    id: number;
    name: string;
}

interface ServiceOption {
    id: number;
    name: string;
}

interface RulesMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Props {
    rules: { data: CommissionRule[]; meta: RulesMeta };
    employees: EmployeeOption[];
    services: ServiceOption[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Nómina', href: '/payroll/dashboard' },
    { title: 'Reglas de Comisión', href: '/payroll/commission-rules' },
];

export default function Index({ rules, employees, services }: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [editRule, setEditRule] = useState<CommissionRule | null>(null);
    const [precedenceOpen, setPrecedenceOpen] = useState(false);

    const handleToggle = (rule: CommissionRule) => {
        router.delete(destroyRule.url(rule), { preserveScroll: true });
    };

    const columns: Column<CommissionRule>[] = [
        {
            key: 'scope_type',
            label: 'Alcance',
            render: (r) => <ScopeTypeBadge scope={r.scope_type} />,
        },
        {
            key: 'target',
            label: 'Aplica a',
            render: (r) => (
                <div className="text-sm text-muted-foreground">
                    {r.employee && <span className="block">{r.employee.name}</span>}
                    {r.service && <span className="block">{r.service.name}</span>}
                    {!r.employee && !r.service && <span>Todos</span>}
                </div>
            ),
        },
        {
            key: 'type',
            label: 'Tipo',
            render: (r) => (
                <span className="text-sm">
                    {r.type === 'percentage' ? `${r.value}%` : `$${r.value} fijo`}
                </span>
            ),
        },
        {
            key: 'effective_from',
            label: 'Vigencia',
            render: (r) => (
                <div className="text-xs text-muted-foreground">
                    <span>Desde: {r.effective_from ?? '—'}</span>
                    {r.effective_until && <span className="block">Hasta: {r.effective_until}</span>}
                </div>
            ),
        },
        {
            key: 'is_active',
            label: 'Estado',
            render: (r) => (
                <Badge className={r.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-300' : 'bg-secondary text-secondary-foreground'}>
                    {r.is_active ? 'Activa' : 'Inactiva'}
                </Badge>
            ),
        },
        {
            key: 'actions',
            label: '',
            render: (r) => (
                <div className="flex items-center gap-2">
                    <Button size="sm" variant="outline" onClick={() => setEditRule(r)}>
                        Editar
                    </Button>
                    <Button
                        size="sm"
                        variant="ghost"
                        onClick={() => handleToggle(r)}
                        className={r.is_active ? 'text-destructive hover:text-destructive' : ''}
                    >
                        {r.is_active ? 'Desactivar' : 'Activar'}
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <AppLayout title="Reglas de Comisión" breadcrumbs={breadcrumbs}>
            <Head title="Reglas de Comisión" />
            <div className="mx-auto max-w-7xl space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Reglas de Comisión</h1>
                        <p className="text-sm text-muted-foreground">Define cómo se calculan las comisiones de cada empleado</p>
                    </div>
                    <Button onClick={() => setCreateOpen(true)}>
                        <Plus className="mr-2 h-4 w-4" />
                        Nueva Regla
                    </Button>
                </div>

                {/* Precedence info */}
                <Collapsible open={precedenceOpen} onOpenChange={setPrecedenceOpen}>
                    <CollapsibleTrigger asChild>
                        <Button variant="outline" size="sm" className="flex items-center gap-2">
                            <Info className="h-4 w-4" />
                            ¿Cómo funciona la precedencia?
                            <ChevronDown className={`h-4 w-4 transition-transform ${precedenceOpen ? 'rotate-180' : ''}`} />
                        </Button>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <Card className="mt-2">
                            <CardContent className="p-4 text-sm text-muted-foreground">
                                <p className="mb-2 font-medium text-foreground">Orden de prioridad (mayor gana):</p>
                                <ol className="space-y-1">
                                    <li>
                                        <strong>4. Específica</strong> — aplica a un empleado + un servicio en particular
                                    </li>
                                    <li>
                                        <strong>3. Por Empleado</strong> — aplica a todos los servicios de un empleado
                                    </li>
                                    <li>
                                        <strong>2. Por Servicio</strong> — aplica a todos los empleados que realicen este servicio
                                    </li>
                                    <li>
                                        <strong>1. Global</strong> — aplica a todos los empleados y servicios
                                    </li>
                                </ol>
                            </CardContent>
                        </Card>
                    </CollapsibleContent>
                </Collapsible>

                {/* Table */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm font-medium text-muted-foreground">
                            {rules.meta.total} regla{rules.meta.total !== 1 ? 's' : ''} configurada{rules.meta.total !== 1 ? 's' : ''}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <DataTable
                            data={rules.data as unknown as Record<string, unknown>[]}
                            columns={columns as Column<Record<string, unknown>>[]}
                            emptyState={
                                <EmptyState
                                    icon={Percent}
                                    title="Sin reglas de comisión"
                                    description="Crea la primera regla para automatizar el cálculo de comisiones."
                                    action={{ label: '+ Nueva Regla', onClick: () => setCreateOpen(true) }}
                                />
                            }
                            pagination={
                                rules.meta.last_page > 1
                                    ? {
                                          currentPage: rules.meta.current_page,
                                          lastPage: rules.meta.last_page,
                                          onPageChange: (page) =>
                                              router.get('/payroll/commission-rules', { page }, { preserveState: true, replace: true }),
                                      }
                                    : undefined
                            }
                        />
                    </CardContent>
                </Card>
            </div>

            {/* Create modal */}
            <CommissionRuleFormModal
                open={createOpen}
                onOpenChange={setCreateOpen}
                employees={employees}
                services={services}
                storeUrl={storeRule.url()}
            />

            {/* Edit modal */}
            {editRule && (
                <CommissionRuleFormModal
                    open={!!editRule}
                    onOpenChange={(open) => { if (!open) setEditRule(null); }}
                    rule={editRule}
                    employees={employees}
                    services={services}
                    storeUrl={storeRule.url()}
                    updateUrl={updateRule.url(editRule)}
                />
            )}
        </AppLayout>
    );
}
