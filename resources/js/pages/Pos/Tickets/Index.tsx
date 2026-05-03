import { useEffect, useState } from 'react';
import { router, Link } from '@inertiajs/react';
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
import { EmptyState } from '@/components/empty-state';
import { PosTicketStatusBadge } from '@/components/pos/pos-ticket-status-badge';
import { EcfStatusBadge } from '@/components/pos/ecf-status-badge';
import type { BreadcrumbItem } from '@/types';
import { Receipt, CreditCard, Banknote, ArrowLeftRight } from 'lucide-react';
import { format } from 'date-fns';

interface TicketListItem {
    id: number;
    ticket_number: string;
    status: 'open' | 'paid' | 'voided';
    total: string;
    ecf_status: 'na' | 'pending' | 'emitted' | 'error' | 'offline_pending';
    ecf_ncf: string | null;
    ecf_error_message: string | null;
    client_name: string | null;
    employee: { user: { name: string } } | null;
    payments: Array<{ method: 'cash' | 'card' | 'transfer'; amount: string }>;
    created_at: string;
}

interface PaginatedTickets {
    data: TicketListItem[];
    meta: { current_page: number; last_page: number; per_page: number; total: number };
}

interface TicketsIndexProps {
    tickets: PaginatedTickets;
    filters: {
        search?: string | null;
        method?: string | null;
        ecf_status?: string | null;
        date?: string | null;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'POS', href: '/pos' },
    { title: 'Tickets', href: '/pos/tickets' },
];

const METHOD_ICONS: Record<string, React.ReactNode> = {
    cash: <Banknote className="inline h-4 w-4 text-[var(--color-green-brand)]" />,
    card: <CreditCard className="inline h-4 w-4 text-blue-500" />,
    transfer: <ArrowLeftRight className="inline h-4 w-4 text-purple-500" />,
};

function formatCurrency(amount: string): string {
    const num = parseFloat(amount);
    return 'RD$' + num.toLocaleString('es-DO', { minimumFractionDigits: 2 });
}

export default function TicketsIndex({ tickets, filters }: TicketsIndexProps) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [method, setMethod] = useState(filters.method ?? '');
    const [ecfStatus, setEcfStatus] = useState(filters.ecf_status ?? '');
    const [date, setDate] = useState(filters.date ?? '');

    // Debounce search input
    useEffect(() => {
        const timer = setTimeout(() => {
            applyFilters({ search });
        }, 300);
        return () => clearTimeout(timer);
    }, [search]);

    function applyFilters(overrides: Partial<typeof filters> = {}) {
        const current = { search, method, ecf_status: ecfStatus, date };
        const merged = { ...current, ...overrides };

        const params: Record<string, string> = {};
        if (merged.search) { params.search = merged.search; }
        if (merged.method) { params.method = merged.method; }
        if (merged.ecf_status) { params.ecf_status = merged.ecf_status; }
        if (merged.date) { params.date = merged.date; }

        router.get('/pos/tickets', params, { preserveState: true, replace: true });
    }

    function handleMethodChange(value: string) {
        setMethod(value);
        applyFilters({ method: value });
    }

    function handleEcfStatusChange(value: string) {
        setEcfStatus(value);
        applyFilters({ ecf_status: value });
    }

    function handleDateChange(e: React.ChangeEvent<HTMLInputElement>) {
        setDate(e.target.value);
        applyFilters({ date: e.target.value });
    }

    const { meta } = tickets;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="space-y-6 p-6">
                {/* Page header */}
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-foreground">Tickets</h1>
                    <p className="text-sm text-muted-foreground">Historial de cobros</p>
                </div>

                {/* Filters */}
                <div className="rounded-lg border border-border bg-card p-4">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Input
                            placeholder="Buscar cliente / ticket #..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />

                        <Select value={method} onValueChange={handleMethodChange}>
                            <SelectTrigger>
                                <SelectValue placeholder="Todos los métodos" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="">Todos los métodos</SelectItem>
                                <SelectItem value="cash">Efectivo</SelectItem>
                                <SelectItem value="card">Tarjeta</SelectItem>
                                <SelectItem value="transfer">Transferencia</SelectItem>
                            </SelectContent>
                        </Select>

                        <Select value={ecfStatus} onValueChange={handleEcfStatusChange}>
                            <SelectTrigger>
                                <SelectValue placeholder="Todos los e-CF" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="">Todos los e-CF</SelectItem>
                                <SelectItem value="emitted">Emitida</SelectItem>
                                <SelectItem value="pending">Pendiente</SelectItem>
                                <SelectItem value="error">Error</SelectItem>
                                <SelectItem value="na">N/A</SelectItem>
                            </SelectContent>
                        </Select>

                        <Input
                            type="date"
                            value={date}
                            onChange={handleDateChange}
                        />
                    </div>
                </div>

                {/* Table */}
                {tickets.data.length === 0 ? (
                    <EmptyState
                        icon={Receipt}
                        title="Sin tickets"
                        description="No se encontraron tickets con los filtros actuales"
                    />
                ) : (
                    <div className="rounded-lg border border-border bg-card overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-border bg-muted/50">
                                        <th className="px-4 py-3 text-left font-medium text-muted-foreground"># Ticket</th>
                                        <th className="px-4 py-3 text-left font-medium text-muted-foreground">Cliente</th>
                                        <th className="px-4 py-3 text-left font-medium text-muted-foreground">Empleado</th>
                                        <th className="px-4 py-3 text-right font-medium text-muted-foreground">Total</th>
                                        <th className="px-4 py-3 text-left font-medium text-muted-foreground">Método</th>
                                        <th className="px-4 py-3 text-left font-medium text-muted-foreground">e-CF</th>
                                        <th className="px-4 py-3 text-left font-medium text-muted-foreground">Estado</th>
                                        <th className="px-4 py-3 text-left font-medium text-muted-foreground">Fecha</th>
                                        <th className="px-4 py-3 text-right font-medium text-muted-foreground">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {tickets.data.map((ticket) => (
                                        <tr
                                            key={ticket.id}
                                            className="border-b border-border last:border-0 hover:bg-muted/30 transition-colors"
                                        >
                                            <td className="px-4 py-3 font-medium text-foreground">
                                                <Link
                                                    href={`/pos/tickets/${ticket.id}`}
                                                    className="text-primary hover:underline"
                                                >
                                                    {ticket.ticket_number}
                                                </Link>
                                            </td>
                                            <td className="px-4 py-3 text-foreground">
                                                {ticket.client_name ?? (
                                                    <span className="text-muted-foreground italic">Walk-In</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-foreground">
                                                {ticket.employee?.user.name ?? (
                                                    <span className="text-muted-foreground">—</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right font-medium text-foreground">
                                                {formatCurrency(ticket.total)}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex gap-1">
                                                    {ticket.payments.map((p, i) => (
                                                        <span key={i} title={p.method}>
                                                            {METHOD_ICONS[p.method]}
                                                        </span>
                                                    ))}
                                                    {ticket.payments.length === 0 && (
                                                        <span className="text-muted-foreground">—</span>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <EcfStatusBadge
                                                    status={ticket.ecf_status}
                                                    errorMessage={ticket.ecf_error_message ?? undefined}
                                                />
                                            </td>
                                            <td className="px-4 py-3">
                                                <PosTicketStatusBadge status={ticket.status} />
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {format(new Date(ticket.created_at), 'dd/MM/yyyy HH:mm')}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <Link
                                                    href={`/pos/tickets/${ticket.id}`}
                                                    className="text-primary hover:underline text-sm"
                                                >
                                                    Ver →
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        {meta.last_page > 1 && (
                            <div className="flex items-center justify-between border-t border-border px-4 py-3">
                                <p className="text-sm text-muted-foreground">
                                    Página {meta.current_page} de {meta.last_page}
                                    {' '}({meta.total} tickets)
                                </p>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={meta.current_page <= 1}
                                        onClick={() => {
                                            const params: Record<string, string | number> = { page: meta.current_page - 1 };
                                            if (search) { params.search = search; }
                                            if (method) { params.method = method; }
                                            if (ecfStatus) { params.ecf_status = ecfStatus; }
                                            if (date) { params.date = date; }
                                            router.get('/pos/tickets', params, { preserveState: true });
                                        }}
                                    >
                                        Anterior
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={meta.current_page >= meta.last_page}
                                        onClick={() => {
                                            const params: Record<string, string | number> = { page: meta.current_page + 1 };
                                            if (search) { params.search = search; }
                                            if (method) { params.method = method; }
                                            if (ecfStatus) { params.ecf_status = ecfStatus; }
                                            if (date) { params.date = date; }
                                            router.get('/pos/tickets', params, { preserveState: true });
                                        }}
                                    >
                                        Siguiente
                                    </Button>
                                </div>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
