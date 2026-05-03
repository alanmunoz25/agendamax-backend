import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { Minus, Plus, ShoppingCart, X } from 'lucide-react';
import { useState, useMemo } from 'react';

export interface ServiceForWalkIn {
    id: number;
    name: string;
    price: string;
    category: string | null;
    service_category_id: number | null;
}

export interface ProductForWalkIn {
    id: number;
    name: string;
    price: string;
    category: string | null;
}

export interface ServiceCategoryItem {
    id: number;
    name: string;
}

export interface WalkInItem {
    id: number;
    type: 'service' | 'product';
    name: string;
    unit_price: string;
    qty: number;
}

interface WalkInPanelProps {
    services: ServiceForWalkIn[];
    products: ProductForWalkIn[];
    categories: ServiceCategoryItem[];
    onOpenCheckout: (items: WalkInItem[]) => void;
    isLoadingCatalog: boolean;
}

function fmt(price: string): string {
    return 'RD$' + Number(price).toLocaleString('es-DO', { minimumFractionDigits: 2 });
}

export function WalkInPanel({
    services,
    products,
    categories,
    onOpenCheckout,
    isLoadingCatalog,
}: WalkInPanelProps) {
    const [selectedCategory, setSelectedCategory] = useState<number | null | 'products'>(null);
    const [searchQuery, setSearchQuery] = useState('');
    const [items, setItems] = useState<WalkInItem[]>([]);

    const filteredItems = useMemo(() => {
        const query = searchQuery.toLowerCase();

        if (selectedCategory === 'products') {
            return products
                .filter((p) => !query || p.name.toLowerCase().includes(query))
                .map((p) => ({ ...p, type: 'product' as const }));
        }

        return services
            .filter((s) => {
                const matchesCategory =
                    selectedCategory === null || s.service_category_id === selectedCategory;
                const matchesQuery = !query || s.name.toLowerCase().includes(query);
                return matchesCategory && matchesQuery;
            })
            .map((s) => ({ ...s, type: 'service' as const }));
    }, [services, products, selectedCategory, searchQuery]);

    const getItemQty = (id: number, type: 'service' | 'product'): number => {
        return items.find((i) => i.id === id && i.type === type)?.qty ?? 0;
    };

    const addItem = (id: number, type: 'service' | 'product', name: string, price: string) => {
        setItems((prev) => {
            const existing = prev.find((i) => i.id === id && i.type === type);
            if (existing) {
                return prev.map((i) =>
                    i.id === id && i.type === type ? { ...i, qty: i.qty + 1 } : i,
                );
            }
            return [...prev, { id, type, name, unit_price: price, qty: 1 }];
        });
    };

    const decreaseItem = (id: number, type: 'service' | 'product') => {
        setItems((prev) => {
            const existing = prev.find((i) => i.id === id && i.type === type);
            if (!existing) return prev;
            if (existing.qty <= 1) {
                return prev.filter((i) => !(i.id === id && i.type === type));
            }
            return prev.map((i) =>
                i.id === id && i.type === type ? { ...i, qty: i.qty - 1 } : i,
            );
        });
    };

    const total = items.reduce((sum, i) => sum + Number(i.unit_price) * i.qty, 0);

    const activeCategoryClass = 'bg-[var(--color-blue-brand)] text-white border-transparent';
    const inactiveCategoryClass =
        'bg-background text-foreground border-border hover:bg-accent cursor-pointer';

    return (
        <div className="flex h-full flex-col">
            <div className="border-b border-border p-3">
                <Input
                    placeholder="Buscar servicio o producto..."
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    className="h-8 text-sm"
                />
            </div>

            <div className="border-b border-border px-3 py-2">
                <div className="flex gap-1.5 overflow-x-auto pb-1">
                    <button
                        onClick={() => setSelectedCategory(null)}
                        className={`inline-flex shrink-0 items-center rounded-md border px-2.5 py-0.5 text-xs font-medium transition-colors ${selectedCategory === null ? activeCategoryClass : inactiveCategoryClass}`}
                    >
                        Todos
                    </button>
                    {categories.map((cat) => (
                        <button
                            key={cat.id}
                            onClick={() => setSelectedCategory(cat.id)}
                            className={`inline-flex shrink-0 items-center rounded-md border px-2.5 py-0.5 text-xs font-medium transition-colors ${selectedCategory === cat.id ? activeCategoryClass : inactiveCategoryClass}`}
                        >
                            {cat.name}
                        </button>
                    ))}
                    <button
                        onClick={() => setSelectedCategory('products')}
                        className={`inline-flex shrink-0 items-center rounded-md border px-2.5 py-0.5 text-xs font-medium transition-colors ${selectedCategory === 'products' ? activeCategoryClass : inactiveCategoryClass}`}
                    >
                        Productos
                    </button>
                </div>
            </div>

            <div className="flex-1 overflow-y-auto">
                {isLoadingCatalog ? (
                    <div className="space-y-2 p-3">
                        {[...Array(6)].map((_, i) => (
                            <Skeleton key={i} className="h-10 w-full rounded-md" />
                        ))}
                    </div>
                ) : (
                    <div className="divide-y divide-border">
                        {filteredItems.length === 0 && (
                            <p className="p-4 text-center text-sm text-muted-foreground">
                                Sin resultados
                            </p>
                        )}
                        {filteredItems.map((item) => {
                            const qty = getItemQty(item.id, item.type);
                            return (
                                <div
                                    key={`${item.type}-${item.id}`}
                                    className="flex items-center justify-between px-3 py-2.5"
                                >
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium text-foreground">
                                            {item.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {fmt(item.price)}
                                        </p>
                                    </div>
                                    <div className="ml-3 shrink-0">
                                        {qty === 0 ? (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="h-7 w-7 p-0"
                                                onClick={() =>
                                                    addItem(
                                                        item.id,
                                                        item.type,
                                                        item.name,
                                                        item.price,
                                                    )
                                                }
                                            >
                                                <Plus className="size-3.5" />
                                            </Button>
                                        ) : (
                                            <div className="flex items-center gap-1">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    className="h-7 w-7 p-0"
                                                    onClick={() =>
                                                        decreaseItem(item.id, item.type)
                                                    }
                                                >
                                                    <Minus className="size-3.5" />
                                                </Button>
                                                <span className="min-w-[1.5rem] text-center text-sm font-medium">
                                                    {qty}
                                                </span>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    className="h-7 w-7 p-0"
                                                    onClick={() =>
                                                        addItem(
                                                            item.id,
                                                            item.type,
                                                            item.name,
                                                            item.price,
                                                        )
                                                    }
                                                >
                                                    <Plus className="size-3.5" />
                                                </Button>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>

            {items.length > 0 && (
                <div className="border-t border-border bg-card p-3">
                    <div className="flex items-center justify-between gap-2">
                        <div className="flex items-center gap-2 min-w-0">
                            <ShoppingCart className="size-4 shrink-0 text-muted-foreground" />
                            <span className="truncate text-sm font-medium">
                                {items.length} {items.length === 1 ? 'item' : 'items'} ·{' '}
                                {fmt(total.toFixed(2))}
                            </span>
                        </div>
                        <div className="flex items-center gap-2 shrink-0">
                            <Button
                                variant="ghost"
                                size="sm"
                                className="h-8 text-xs"
                                onClick={() => setItems([])}
                            >
                                <X className="size-3.5" />
                                Limpiar
                            </Button>
                            <Button
                                size="sm"
                                className="h-8 bg-[var(--color-blue-brand)] text-white hover:bg-[var(--color-blue-brand)]/90 text-xs"
                                onClick={() => onOpenCheckout(items)}
                                disabled={items.length === 0}
                            >
                                Abrir Checkout →
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
