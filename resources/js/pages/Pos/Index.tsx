import { AppointmentCard, AppointmentForPos } from '@/components/pos/appointment-card';
import { CheckoutDrawer, CheckoutItem } from '@/components/pos/checkout-drawer';
import { ServiceCategoryItem, ServiceForWalkIn, WalkInItem, WalkInPanel } from '@/components/pos/walk-in-panel';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Banknote, Calendar, TriangleAlert, WifiOff } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { format } from 'date-fns';
import { EmptyState } from '@/components/empty-state';

interface ProductForWalkIn {
    id: number;
    name: string;
    price: string;
    category: string | null;
}

interface PosIndexProps {
    today_appointments: AppointmentForPos[];
    services_catalog: ServiceForWalkIn[] | null;
    products_catalog: ProductForWalkIn[] | null;
    service_categories: ServiceCategoryItem[];
    employees_for_walkin: Array<{ id: number; user: { name: string } }>;
    today_summary: {
        total_tickets: number;
        uncollected_count: number;
        collected_count: number;
        total_sales_today: string;
    };
    has_open_shift: boolean;
    ecf_enabled: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Punto de Venta', href: '/pos' },
];

function OfflineBanner() {
    const [isOffline, setIsOffline] = useState(false);

    useEffect(() => {
        setIsOffline(!navigator.onLine);

        const handleOffline = () => setIsOffline(true);
        const handleOnline = () => setIsOffline(false);

        window.addEventListener('offline', handleOffline);
        window.addEventListener('online', handleOnline);

        return () => {
            window.removeEventListener('offline', handleOffline);
            window.removeEventListener('online', handleOnline);
        };
    }, []);

    if (!isOffline) return null;

    return (
        <div className="flex items-center gap-2 bg-[var(--color-amber-brand)]/10 border-b border-[var(--color-amber-brand)]/30 px-4 py-2 text-sm text-[var(--color-amber-brand)]">
            <WifiOff className="size-4 shrink-0" />
            <span>
                <strong>Sin conexión</strong> — El POS opera en modo offline. Los tickets no
                pueden procesarse. La emisión de e-CF no está disponible.
            </span>
        </div>
    );
}

export default function PosIndex() {
    const {
        today_appointments,
        services_catalog,
        products_catalog,
        service_categories,
        employees_for_walkin,
        today_summary,
        has_open_shift,
        ecf_enabled,
    } = usePage<SharedData & PosIndexProps>().props;

    const [drawerOpen, setDrawerOpen] = useState(false);
    const [checkoutSource, setCheckoutSource] = useState<'appointment' | 'walkin'>('walkin');
    const [selectedAppointment, setSelectedAppointment] = useState<AppointmentForPos | null>(null);
    const [checkoutItems, setCheckoutItems] = useState<CheckoutItem[]>([]);
    const [walkInItems, setWalkInItems] = useState<WalkInItem[]>([]);
    const [searchQuery, setSearchQuery] = useState('');

    const filteredAppointments = useMemo(() => {
        if (!searchQuery) return today_appointments;
        const query = searchQuery.toLowerCase();
        return today_appointments.filter((appt) => {
            const clientMatch = appt.client?.name.toLowerCase().includes(query);
            const serviceMatch = appt.services.some((s) =>
                s.name.toLowerCase().includes(query),
            );
            return clientMatch || serviceMatch;
        });
    }, [today_appointments, searchQuery]);

    function handleCheckoutFromAppointment(appt: AppointmentForPos) {
        setCheckoutSource('appointment');
        setSelectedAppointment(appt);
        setCheckoutItems(
            appt.services.map((s) => ({
                id: s.id,
                type: 'service' as const,
                name: s.name,
                unit_price: s.price,
                qty: 1,
            })),
        );
        setDrawerOpen(true);
    }

    function handleCheckoutFromWalkIn(items: WalkInItem[]) {
        setCheckoutSource('walkin');
        setSelectedAppointment(null);
        setCheckoutItems(items);
        setDrawerOpen(true);
    }

    function handleWalkInItemsChange(items: WalkInItem[]) {
        setWalkInItems(items);
        // Keep checkoutItems in sync so CheckoutDrawer reflects real-time walk-in items
        if (checkoutSource === 'walkin') {
            setCheckoutItems(
                items.map((i) => ({
                    id: i.id,
                    type: i.type,
                    name: i.name,
                    unit_price: i.unit_price,
                    qty: i.qty,
                })),
            );
        }
    }

    function handleCheckoutSuccess() {
        setWalkInItems([]);
        setDrawerOpen(false);
        router.reload();
    }

    const uncollectedCount = today_summary?.uncollected_count ?? 0;
    const totalSalesToday = today_summary?.total_sales_today ?? '0.00';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Punto de Venta" />

            <OfflineBanner />

            {!has_open_shift && (
                <div className="flex items-center gap-2 bg-[var(--color-amber-brand)]/10 border-b border-[var(--color-amber-brand)]/30 px-4 py-2 text-sm text-[var(--color-amber-brand)]">
                    <TriangleAlert className="size-4 shrink-0" />
                    <span>
                        No hay un turno abierto. Registra un cierre de turno al finalizar el día.
                    </span>
                </div>
            )}

            <div className="flex items-center justify-between border-b border-border px-4 py-3">
                <div>
                    <h1 className="text-xl font-bold text-foreground">Punto de Venta</h1>
                    <p className="text-sm text-muted-foreground">
                        {format(new Date(), "EEEE, d 'de' MMMM yyyy")}
                    </p>
                </div>
                <div className="flex items-center gap-3">
                    {uncollectedCount > 0 && (
                        <Badge className="bg-[var(--color-amber-brand)]/10 text-[var(--color-amber-brand)]">
                            {uncollectedCount} sin cobrar
                        </Badge>
                    )}
                    <Badge className="bg-secondary text-secondary-foreground">
                        RD$
                        {Number(totalSalesToday).toLocaleString('es-DO', {
                            minimumFractionDigits: 2,
                        })}
                    </Badge>
                    <Link href="/pos/shift-close">
                        <Button variant="outline" size="sm">
                            <Banknote className="size-4" />
                            Cierre de Turno
                        </Button>
                    </Link>
                </div>
            </div>

            <div className="flex overflow-hidden" style={{ height: 'calc(100vh - 140px)' }}>
                {/* Left panel — appointments */}
                <div className="flex w-[60%] flex-col border-r border-border">
                    <div className="flex items-center justify-between border-b border-border p-4">
                        <h2 className="font-semibold text-foreground">Citas del día</h2>
                        <Input
                            placeholder="Buscar cliente..."
                            className="h-8 w-48 text-sm"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                        />
                    </div>
                    <div className="flex-1 space-y-3 overflow-y-auto p-4">
                        {filteredAppointments.length === 0 ? (
                            <EmptyState
                                icon={Calendar}
                                title="Sin citas para hoy"
                                description="Todos los cobros se realizan como walk-in"
                            />
                        ) : (
                            filteredAppointments.map((appt) => (
                                <AppointmentCard
                                    key={appt.id}
                                    appointment={appt}
                                    onCheckout={handleCheckoutFromAppointment}
                                />
                            ))
                        )}
                    </div>
                </div>

                {/* Right panel — walk-in catalog */}
                <div className="flex w-[40%] flex-col">
                    <WalkInPanel
                        services={services_catalog ?? []}
                        products={products_catalog ?? []}
                        categories={service_categories}
                        items={walkInItems}
                        onItemsChange={handleWalkInItemsChange}
                        onOpenCheckout={handleCheckoutFromWalkIn}
                        isLoadingCatalog={services_catalog === null}
                    />
                </div>
            </div>

            <CheckoutDrawer
                open={drawerOpen}
                onOpenChange={setDrawerOpen}
                source={checkoutSource}
                appointmentId={selectedAppointment?.id ?? null}
                initialItems={checkoutItems}
                client={selectedAppointment?.client ?? null}
                employee={selectedAppointment?.employee ?? null}
                employees={employees_for_walkin}
                ecfEnabled={ecf_enabled}
                onSuccess={handleCheckoutSuccess}
            />
        </AppLayout>
    );
}
