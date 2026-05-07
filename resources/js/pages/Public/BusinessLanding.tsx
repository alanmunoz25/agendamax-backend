import { useState, useMemo } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Clock, DollarSign, MapPin } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface ServiceCategory {
    id: number;
    name: string;
    parent_id: number | null;
    children?: ServiceCategory[];
}

interface Service {
    id: number;
    name: string;
    description: string | null;
    price: number | null;
    duration: number;
    service_category_id: number | null;
    service_category?: {
        id: number;
        name: string;
        parent?: ServiceCategory | null;
    } | null;
}

interface Employee {
    id: number;
    name: string | null;
    photo_url: string | null;
    bio: string | null;
}

interface BusinessData {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    address: string | null;
    logo_url: string | null;
    banner_url: string | null;
    cover_image_url: string | null;
    sector: string | null;
    province: string | null;
    country: string;
    latitude: number | null;
    longitude: number | null;
}

interface Props {
    business: BusinessData;
    services: Service[];
    employees: Employee[];
    categories: ServiceCategory[];
}

function getInitials(name: string | null): string {
    if (!name) {
        return '?';
    }
    return name
        .split(' ')
        .slice(0, 2)
        .map((n) => n[0])
        .join('')
        .toUpperCase();
}

export default function BusinessLanding({ business, services, employees, categories }: Props) {
    const { t } = useTranslation();

    const [selectedParentId, setSelectedParentId] = useState<number | null>(null);
    const [selectedChildId, setSelectedChildId] = useState<number | null>(null);

    const mapsUrl =
        business.latitude && business.longitude
            ? `https://www.google.com/maps/?q=${business.latitude},${business.longitude}`
            : null;

    const bookingUrl = `/login?redirect=/negocio/${business.slug}`;
    const heroImage = business.cover_image_url ?? business.banner_url ?? null;

    // Parent categories that actually have services
    const parentCategories = useMemo(() => {
        const parentIdsWithServices = new Set<number | null>();
        services.forEach((s) => {
            const cat = s.service_category;
            if (cat) {
                const parentId = cat.parent?.id ?? cat.id;
                parentIdsWithServices.add(parentId);
            } else {
                parentIdsWithServices.add(null);
            }
        });

        const result: (ServiceCategory | null)[] = [];

        // Top-level categories from the categories list that have services
        categories.forEach((c) => {
            if (parentIdsWithServices.has(c.id)) {
                result.push(c);
            }
        });

        // If there are uncategorized services, add a pseudo-null entry
        if (parentIdsWithServices.has(null)) {
            result.push(null);
        }

        return result;
    }, [categories, services]);

    // Children of selected parent
    const childCategories = useMemo(() => {
        if (selectedParentId === null) {
            return [];
        }
        const parent = categories.find((c) => c.id === selectedParentId);
        if (!parent?.children?.length) {
            return [];
        }
        // Only children that have services
        const childIdsWithServices = new Set<number>();
        services.forEach((s) => {
            const cat = s.service_category;
            if (cat?.parent?.id === selectedParentId) {
                childIdsWithServices.add(cat.id);
            }
        });
        return parent.children.filter((ch) => childIdsWithServices.has(ch.id));
    }, [selectedParentId, categories, services]);

    // Filtered services based on selected categories
    const filteredServices = useMemo(() => {
        if (selectedParentId === null && selectedChildId === null) {
            return services;
        }

        return services.filter((s) => {
            const cat = s.service_category;

            // Uncategorized filter
            if (selectedParentId === null) {
                return !cat;
            }

            if (!cat) {
                return false;
            }

            const parentId = cat.parent?.id ?? cat.id;

            if (parentId !== selectedParentId) {
                return false;
            }

            if (selectedChildId !== null) {
                return cat.id === selectedChildId;
            }

            return true;
        });
    }, [services, selectedParentId, selectedChildId]);

    const handleParentSelect = (id: number | null) => {
        if (selectedParentId === id) {
            // Deselect → show all
            setSelectedParentId(null);
            setSelectedChildId(null);
        } else {
            setSelectedParentId(id);
            setSelectedChildId(null);
        }
    };

    const handleChildSelect = (id: number) => {
        if (selectedChildId === id) {
            setSelectedChildId(null);
        } else {
            setSelectedChildId(id);
        }
    };

    return (
        <>
            <Head>
                <title>{`${business.name} - AgendaMax`}</title>
                {business.description && (
                    <meta name="description" content={business.description} />
                )}
                {business.logo_url && (
                    <meta property="og:image" content={business.logo_url} />
                )}
                <meta property="og:title" content={`${business.name} - AgendaMax`} />
            </Head>

            <div className="min-h-screen bg-background text-foreground">
                {/* Header */}
                <header className="sticky top-0 z-50 border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
                    <div className="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
                        <div className="flex items-center gap-3">
                            {business.logo_url && (
                                <img
                                    src={business.logo_url}
                                    alt={business.name}
                                    className="h-9 w-9 rounded-full object-cover"
                                />
                            )}
                            <span className="text-lg font-semibold">{business.name}</span>
                        </div>
                        <Link href={bookingUrl}>
                            <Button size="sm">{t('public.business.book_now')}</Button>
                        </Link>
                    </div>
                </header>

                {/* Hero */}
                <section
                    className="relative border-b"
                    style={
                        heroImage
                            ? {
                                  backgroundImage: `url(${heroImage})`,
                                  backgroundSize: 'cover',
                                  backgroundPosition: 'center',
                              }
                            : undefined
                    }
                >
                    {heroImage && <div className="absolute inset-0 bg-black/50" />}
                    <div
                        className={[
                            'relative mx-auto max-w-6xl px-4 py-12 md:py-16',
                            heroImage ? '' : 'bg-muted/30 dark:bg-muted/10',
                        ]
                            .join(' ')
                            .trim()}
                    >
                        <div className="flex flex-col gap-6 md:flex-row md:items-center md:gap-10">
                            {business.logo_url && (
                                <img
                                    src={business.logo_url}
                                    alt={business.name}
                                    className="h-24 w-24 shrink-0 rounded-2xl object-cover shadow-md md:h-32 md:w-32"
                                />
                            )}
                            <div
                                className={[
                                    'flex flex-col gap-3',
                                    heroImage ? 'text-white' : '',
                                ]
                                    .join(' ')
                                    .trim()}
                            >
                                <h1 className="text-3xl font-bold tracking-tight md:text-4xl">
                                    {business.name}
                                </h1>
                                {business.description && (
                                    <p
                                        className={[
                                            'max-w-xl',
                                            heroImage ? 'text-white/80' : 'text-muted-foreground',
                                        ].join(' ')}
                                    >
                                        {business.description}
                                    </p>
                                )}
                                <div className="flex flex-wrap gap-2">
                                    {business.sector && (
                                        <Badge variant="secondary">{business.sector}</Badge>
                                    )}
                                    {business.province && (
                                        <Badge variant="outline">
                                            <MapPin className="mr-1 size-3" />
                                            {business.province}
                                        </Badge>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Main content */}
                <main className="mx-auto max-w-6xl px-4 py-10 space-y-12">
                    {/* Services section */}
                    {services.length > 0 && (
                        <section>
                            <h2 className="mb-6 text-2xl font-bold">
                                {t('public.business.services_title')}
                            </h2>

                            {/* Category filter chips — parent level */}
                            {parentCategories.length > 1 && (
                                <div className="mb-4 flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setSelectedParentId(null);
                                            setSelectedChildId(null);
                                        }}
                                        className={[
                                            'rounded-full px-4 py-1.5 text-sm font-medium transition-colors',
                                            selectedParentId === null && selectedChildId === null
                                                ? 'bg-foreground text-background'
                                                : 'border border-border bg-background text-foreground hover:bg-muted',
                                        ].join(' ')}
                                    >
                                        {t('public.business.filter_all')}
                                    </button>

                                    {parentCategories.map((cat) => {
                                        const id = cat?.id ?? null;
                                        const name = cat?.name ?? t('public.business.category_other');
                                        const isActive = selectedParentId === id && !(id === null);

                                        return (
                                            <button
                                                key={id ?? 'uncategorized'}
                                                type="button"
                                                onClick={() => handleParentSelect(id)}
                                                className={[
                                                    'rounded-full px-4 py-1.5 text-sm font-medium transition-colors',
                                                    isActive
                                                        ? 'bg-foreground text-background'
                                                        : 'border border-border bg-background text-foreground hover:bg-muted',
                                                ].join(' ')}
                                            >
                                                {name}
                                            </button>
                                        );
                                    })}
                                </div>
                            )}

                            {/* Sub-category chips — appear when parent is selected */}
                            {selectedParentId !== null && childCategories.length > 1 && (
                                <div className="mb-6 flex flex-wrap gap-2 pl-1">
                                    <button
                                        type="button"
                                        onClick={() => setSelectedChildId(null)}
                                        className={[
                                            'rounded-full px-3 py-1 text-xs font-medium transition-colors',
                                            selectedChildId === null
                                                ? 'bg-primary text-primary-foreground'
                                                : 'border border-border bg-background text-muted-foreground hover:bg-muted',
                                        ].join(' ')}
                                    >
                                        {t('public.business.filter_all')}
                                    </button>

                                    {childCategories.map((child) => (
                                        <button
                                            key={child.id}
                                            type="button"
                                            onClick={() => handleChildSelect(child.id)}
                                            className={[
                                                'rounded-full px-3 py-1 text-xs font-medium transition-colors',
                                                selectedChildId === child.id
                                                    ? 'bg-primary text-primary-foreground'
                                                    : 'border border-border bg-background text-muted-foreground hover:bg-muted',
                                            ].join(' ')}
                                        >
                                            {child.name}
                                        </button>
                                    ))}
                                </div>
                            )}

                            {/* Services grid */}
                            {filteredServices.length === 0 ? (
                                <div className="rounded-xl border border-dashed border-border py-16 text-center">
                                    <p className="text-muted-foreground">
                                        {t('public.business.no_services_in_category')}
                                    </p>
                                </div>
                            ) : (
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    {filteredServices.map((service) => (
                                        <Card
                                            key={service.id}
                                            className="flex flex-col transition-shadow hover:shadow-md"
                                        >
                                            <CardHeader className="pb-2">
                                                <div className="flex items-start justify-between gap-2">
                                                    <CardTitle className="text-base leading-tight">
                                                        {service.name}
                                                    </CardTitle>
                                                    <span className="shrink-0 font-semibold text-foreground">
                                                        {service.price && Number(service.price) > 0
                                                            ? `RD$ ${Number(service.price).toLocaleString('es-DO', { minimumFractionDigits: 0 })}`
                                                            : (
                                                                <Badge variant="secondary">
                                                                    {t('public.business.price_free')}
                                                                </Badge>
                                                            )}
                                                    </span>
                                                </div>
                                                {service.service_category && (
                                                    <p className="text-xs text-muted-foreground">
                                                        {service.service_category.parent
                                                            ? `${service.service_category.parent.name} / ${service.service_category.name}`
                                                            : service.service_category.name}
                                                    </p>
                                                )}
                                            </CardHeader>
                                            <CardContent className="flex flex-col gap-2">
                                                {service.description && (
                                                    <p className="line-clamp-2 text-sm text-muted-foreground">
                                                        {service.description}
                                                    </p>
                                                )}
                                                <div className="mt-auto flex items-center gap-3 pt-2 text-sm text-muted-foreground">
                                                    <span className="flex items-center gap-1">
                                                        <Clock className="size-3.5" />
                                                        {t('public.business.duration_minutes', {
                                                            count: service.duration,
                                                        })}
                                                    </span>
                                                    <span className="flex items-center gap-1">
                                                        <DollarSign className="size-3.5" />
                                                        {service.price && Number(service.price) > 0
                                                            ? `RD$ ${Number(service.price).toLocaleString('es-DO', { minimumFractionDigits: 0 })}`
                                                            : t('public.business.price_free')}
                                                    </span>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    ))}
                                </div>
                            )}
                        </section>
                    )}

                    {/* Team */}
                    {employees.length > 0 && (
                        <section>
                            <h2 className="mb-6 text-2xl font-bold">
                                {t('public.business.team_title')}
                            </h2>
                            <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                                {employees.map((employee) => (
                                    <Card
                                        key={employee.id}
                                        className="flex flex-col items-center p-4 text-center"
                                    >
                                        {employee.photo_url ? (
                                            <img
                                                src={employee.photo_url}
                                                alt={employee.name ?? ''}
                                                className="mb-3 h-16 w-16 rounded-full object-cover"
                                            />
                                        ) : (
                                            <div className="mb-3 flex h-16 w-16 items-center justify-center rounded-full bg-primary/10 text-lg font-semibold text-primary">
                                                {getInitials(employee.name)}
                                            </div>
                                        )}
                                        <p className="font-medium leading-tight">
                                            {employee.name ?? '—'}
                                        </p>
                                        {employee.bio && (
                                            <p className="mt-1 line-clamp-2 text-xs text-muted-foreground">
                                                {employee.bio}
                                            </p>
                                        )}
                                    </Card>
                                ))}
                            </div>
                        </section>
                    )}

                    {/* Location */}
                    {(business.address || mapsUrl) && (
                        <section>
                            <h2 className="mb-4 text-2xl font-bold">
                                {t('public.business.location_title')}
                            </h2>
                            <div className="flex flex-col gap-3">
                                {business.address && (
                                    <div className="flex items-start gap-2 text-muted-foreground">
                                        <MapPin className="mt-0.5 size-4 shrink-0" />
                                        <span>{business.address}</span>
                                    </div>
                                )}
                                {mapsUrl && (
                                    <a
                                        href={mapsUrl}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex w-fit items-center gap-1.5 rounded-md border px-3 py-1.5 text-sm font-medium transition-colors hover:bg-muted"
                                    >
                                        <MapPin className="size-4" />
                                        {t('public.business.maps_link')}
                                    </a>
                                )}
                            </div>
                        </section>
                    )}
                </main>

                {/* Footer */}
                <footer className="border-t py-6 text-center text-sm text-muted-foreground">
                    {t('public.business.powered_by')}
                </footer>
            </div>
        </>
    );
}
