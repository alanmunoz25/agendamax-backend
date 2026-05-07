import { Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { MapPin, Navigation, Search } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

interface BusinessItem {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    logo_url: string | null;
    sector: string | null;
    province: string | null;
    address: string | null;
    services_count?: number;
    distance_km?: number | null;
}

interface PaginationMeta {
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
}

interface PaginatedBusinesses {
    data: BusinessItem[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
}

interface Props {
    businesses: PaginatedBusinesses;
    sectors: string[];
    provinces: string[];
}

const ALL_SENTINEL = '__all__';

function getInitials(name: string): string {
    return name
        .split(' ')
        .slice(0, 2)
        .map((n) => n[0])
        .join('')
        .toUpperCase();
}

export default function Discover({ businesses: initialBusinesses, sectors, provinces }: Props) {
    const { t } = useTranslation();

    const [search, setSearch] = useState('');
    const [sector, setSector] = useState('');
    const [province, setProvince] = useState('');
    const [userLat, setUserLat] = useState<number | null>(null);
    const [userLng, setUserLng] = useState<number | null>(null);
    const [geoLoading, setGeoLoading] = useState(false);
    const [geoError, setGeoError] = useState<string | null>(null);

    const [results, setResults] = useState<BusinessItem[]>(initialBusinesses.data);
    const [meta, setMeta] = useState<PaginationMeta>({
        current_page: initialBusinesses.current_page,
        last_page: initialBusinesses.last_page,
        total: initialBusinesses.total,
        per_page: initialBusinesses.per_page,
    });
    const [loading, setLoading] = useState(false);
    const [page, setPage] = useState(1);

    const debounceTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const fetchBusinesses = useCallback(
        async (
            searchTerm: string,
            sectorFilter: string,
            provinceFilter: string,
            lat: number | null,
            lng: number | null,
            pageNum: number,
        ) => {
            setLoading(true);
            try {
                const params = new URLSearchParams();
                if (searchTerm.length >= 2) {
                    params.set('q', searchTerm);
                }
                if (sectorFilter) {
                    params.set('sector', sectorFilter);
                }
                if (provinceFilter) {
                    params.set('province', provinceFilter);
                }
                if (lat !== null && lng !== null) {
                    params.set('lat', String(lat));
                    params.set('lng', String(lng));
                    params.set('radius_km', '25');
                }
                params.set('page', String(pageNum));
                params.set('per_page', '15');

                const response = await fetch(`/api/v1/businesses?${params.toString()}`, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }

                const json = await response.json();

                setResults(json.data ?? []);
                setMeta({
                    current_page: json.meta?.current_page ?? json.current_page ?? 1,
                    last_page: json.meta?.last_page ?? json.last_page ?? 1,
                    total: json.meta?.total ?? json.total ?? 0,
                    per_page: json.meta?.per_page ?? json.per_page ?? 15,
                });
            } catch {
                setResults([]);
            } finally {
                setLoading(false);
            }
        },
        [],
    );

    useEffect(() => {
        if (debounceTimer.current) {
            clearTimeout(debounceTimer.current);
        }
        debounceTimer.current = setTimeout(() => {
            setPage(1);
            fetchBusinesses(search, sector, province, userLat, userLng, 1);
        }, 400);

        return () => {
            if (debounceTimer.current) {
                clearTimeout(debounceTimer.current);
            }
        };
    }, [search, sector, province, userLat, userLng, fetchBusinesses]);

    const handlePageChange = (newPage: number) => {
        setPage(newPage);
        fetchBusinesses(search, sector, province, userLat, userLng, newPage);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const handleUseLocation = () => {
        if (!navigator.geolocation) {
            setGeoError(t('errors.geo_unsupported'));
            return;
        }
        setGeoLoading(true);
        setGeoError(null);
        navigator.geolocation.getCurrentPosition(
            (position) => {
                setUserLat(position.coords.latitude);
                setUserLng(position.coords.longitude);
                setGeoLoading(false);
            },
            () => {
                setGeoError(t('errors.geo_denied'));
                setGeoLoading(false);
            },
        );
    };

    return (
        <>
            <Head>
                <title>{`${t('discover.hero_title')} - AgendaMax`}</title>
                <meta name="description" content={t('discover.hero_subtitle')} />
            </Head>

            <div className="min-h-screen bg-background text-foreground">
                {/* Header */}
                <header className="sticky top-0 z-50 border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
                    <div className="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
                        <span className="text-lg font-semibold">AgendaMax</span>
                        <Link href="/login">
                            <Button size="sm" variant="outline">
                                Iniciar sesión
                            </Button>
                        </Link>
                    </div>
                </header>

                {/* Hero */}
                <section className="border-b bg-muted/30 dark:bg-muted/10">
                    <div className="mx-auto max-w-6xl px-4 py-12 md:py-16">
                        <h1 className="mb-2 text-3xl font-bold tracking-tight md:text-4xl">
                            {t('discover.hero_title')}
                        </h1>
                        <p className="mb-8 text-muted-foreground">{t('discover.hero_subtitle')}</p>

                        {/* Filters */}
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                            <div className="relative flex-1">
                                <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    type="text"
                                    placeholder={t('discover.search_placeholder')}
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="pl-9"
                                />
                            </div>

                            <Select
                                value={sector || ALL_SENTINEL}
                                onValueChange={(v) => setSector(v === ALL_SENTINEL ? '' : v)}
                            >
                                <SelectTrigger className="w-full sm:w-48">
                                    <SelectValue placeholder={t('discover.sector_placeholder')} />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL_SENTINEL}>
                                        {t('discover.sector_placeholder')}
                                    </SelectItem>
                                    {sectors.map((s) => (
                                        <SelectItem key={s} value={s}>
                                            {s}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            <Select
                                value={province || ALL_SENTINEL}
                                onValueChange={(v) => setProvince(v === ALL_SENTINEL ? '' : v)}
                            >
                                <SelectTrigger className="w-full sm:w-52">
                                    <SelectValue placeholder={t('discover.province_placeholder')} />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL_SENTINEL}>
                                        {t('discover.province_placeholder')}
                                    </SelectItem>
                                    {provinces.map((p) => (
                                        <SelectItem key={p} value={p}>
                                            {p}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            <Button
                                variant="outline"
                                onClick={handleUseLocation}
                                disabled={geoLoading}
                                className="shrink-0"
                            >
                                <Navigation className="mr-2 size-4" />
                                {geoLoading ? t('common.loading') : t('discover.use_location')}
                            </Button>
                        </div>

                        {geoError && (
                            <p className="mt-2 text-sm text-destructive">{geoError}</p>
                        )}

                        {userLat !== null && !geoError && (
                            <p className="mt-2 flex items-center gap-1 text-sm text-muted-foreground">
                                <MapPin className="size-3.5" />
                                {t('common.location_captured')}
                            </p>
                        )}
                    </div>
                </section>

                {/* Results */}
                <main className="mx-auto max-w-6xl px-4 py-10">
                    {loading ? (
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {Array.from({ length: 6 }).map((_, i) => (
                                <div
                                    key={i}
                                    className="h-48 animate-pulse rounded-xl bg-primary/10"
                                />
                            ))}
                        </div>
                    ) : results.length === 0 ? (
                        <div className="flex flex-col items-center gap-2 py-20 text-center">
                            <Search className="size-10 text-muted-foreground/50" />
                            <p className="text-lg font-semibold">{t('discover.no_results')}</p>
                            <p className="text-sm text-muted-foreground">
                                {t('discover.no_results_hint')}
                            </p>
                        </div>
                    ) : (
                        <>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {results.map((business) => (
                                    <Link
                                        key={business.id}
                                        href={`/negocio/${business.slug}`}
                                    >
                                        <Card className="group h-full cursor-pointer transition-shadow hover:shadow-md">
                                            <CardContent className="flex flex-col gap-3 p-4">
                                                {/* Logo + Name */}
                                                <div className="flex items-start gap-3">
                                                    {business.logo_url ? (
                                                        <img
                                                            src={business.logo_url}
                                                            alt={business.name}
                                                            className="h-12 w-12 shrink-0 rounded-xl object-cover"
                                                        />
                                                    ) : (
                                                        <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-sm font-bold text-primary">
                                                            {getInitials(business.name)}
                                                        </div>
                                                    )}
                                                    <div className="min-w-0 flex-1">
                                                        <p className="truncate font-semibold leading-tight group-hover:text-primary">
                                                            {business.name}
                                                        </p>
                                                        {business.description && (
                                                            <p className="mt-0.5 line-clamp-2 text-xs text-muted-foreground">
                                                                {business.description}
                                                            </p>
                                                        )}
                                                    </div>
                                                </div>

                                                {/* Badges */}
                                                <div className="flex flex-wrap gap-1.5">
                                                    {business.sector && (
                                                        <Badge variant="secondary">
                                                            {business.sector}
                                                        </Badge>
                                                    )}
                                                    {business.province && (
                                                        <Badge variant="outline">
                                                            <MapPin className="mr-1 size-3" />
                                                            {business.province}
                                                        </Badge>
                                                    )}
                                                </div>

                                                {/* Address / Distance */}
                                                <div className="flex items-center justify-between text-xs text-muted-foreground">
                                                    {business.address && (
                                                        <span className="truncate">
                                                            {business.address}
                                                        </span>
                                                    )}
                                                    {business.distance_km != null && (
                                                        <span className="ml-auto shrink-0 font-medium text-primary">
                                                            {t('discover.distance_km', {
                                                                km: Number(
                                                                    business.distance_km,
                                                                ).toFixed(1),
                                                            })}
                                                        </span>
                                                    )}
                                                </div>
                                            </CardContent>
                                        </Card>
                                    </Link>
                                ))}
                            </div>

                            {/* Pagination */}
                            {meta.last_page > 1 && (
                                <div className="mt-8 flex items-center justify-center gap-3">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={meta.current_page <= 1}
                                        onClick={() => handlePageChange(meta.current_page - 1)}
                                    >
                                        {t('common.previous')}
                                    </Button>
                                    <span className="text-sm text-muted-foreground">
                                        {t('common.page_of', {
                                            current: meta.current_page,
                                            total: meta.last_page,
                                        })}
                                    </span>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={meta.current_page >= meta.last_page}
                                        onClick={() => handlePageChange(meta.current_page + 1)}
                                    >
                                        {t('common.next')}
                                    </Button>
                                </div>
                            )}
                        </>
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
