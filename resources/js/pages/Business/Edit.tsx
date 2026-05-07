import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';
import AppLayout from '@/layouts/app-layout';
import { type Business } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { MapPin, Save, Upload, X } from 'lucide-react';
import { useRef, useState, type FormEventHandler } from 'react';
import { useTranslation } from 'react-i18next';

interface BusinessEditProps {
    business: Business;
}

export default function Edit({ business }: BusinessEditProps) {
    const { t } = useTranslation();

    const breadcrumbs = [
        { title: t('breadcrumbs.dashboard'), href: '/dashboard' },
        { title: t('nav.business'), href: '/business' },
        { title: t('breadcrumbs.edit'), href: '/business/edit' },
    ];

    const { data, setData, post, processing, errors, isDirty } = useForm<{
        name: string;
        description: string;
        email: string;
        phone: string;
        address: string;
        loyalty_stamps_required: number;
        loyalty_reward_description: string;
        sector: string;
        province: string;
        country: string;
        latitude: number | null;
        longitude: number | null;
        logo: File | null;
        banner: File | null;
        cover: File | null;
        _method: string;
    }>({
        name: business.name || '',
        description: business.description || '',
        email: business.email || '',
        phone: business.phone || '',
        address: business.address || '',
        loyalty_stamps_required: business.loyalty_stamps_required || 10,
        loyalty_reward_description:
            business.loyalty_reward_description || '',
        sector: business.sector || '',
        province: business.province || '',
        country: business.country || 'DO',
        latitude: business.latitude ?? null,
        longitude: business.longitude ?? null,
        logo: null,
        banner: null,
        cover: null,
        _method: 'PUT',
    });

    const [logoPreview, setLogoPreview] = useState<string | null>(
        business.logo_url ?? null,
    );
    const [bannerPreview, setBannerPreview] = useState<string | null>(
        (business as Record<string, unknown>).banner_url as string | null ?? null,
    );
    const [coverPreview, setCoverPreview] = useState<string | null>(
        (business as Record<string, unknown>).cover_image_url as string | null ?? null,
    );

    const logoRef = useRef<HTMLInputElement>(null);
    const bannerRef = useRef<HTMLInputElement>(null);
    const coverRef = useRef<HTMLInputElement>(null);

    const handleFileChange = (
        field: 'logo' | 'banner' | 'cover',
        file: File | undefined,
        setPreview: (v: string | null) => void,
    ) => {
        if (!file) {
            return;
        }
        setData(field, file);
        setPreview(URL.createObjectURL(file));
    };

    const handleGetLocation = () => {
        if (!navigator.geolocation) {
            console.error(t('errors.geo_unsupported'));
            return;
        }
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                setData((prev) => ({
                    ...prev,
                    latitude: pos.coords.latitude,
                    longitude: pos.coords.longitude,
                }));
            },
            (_err) => {
                console.error(t('errors.geo_denied'));
            },
        );
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/business', { forceFormData: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('business_profile.edit_title')} />

            <div className="flex flex-col gap-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">
                            {t('business_profile.edit_title')}
                        </h1>
                        <p className="text-muted-foreground">
                            {t('business_profile.edit_subtitle')}
                        </p>
                    </div>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    {/* Images card */}
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('business.images.title')}</CardTitle>
                            <CardDescription>
                                {t('business.images.description')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {/* Logo */}
                            <div className="space-y-2">
                                <Label htmlFor="logo">{t('business.images.logo')}</Label>
                                <div className="flex items-center gap-4">
                                    {logoPreview && (
                                        <img
                                            src={logoPreview}
                                            alt="Logo preview"
                                            className="h-16 w-16 rounded-lg object-cover border"
                                        />
                                    )}
                                    <div className="flex-1">
                                        <input
                                            ref={logoRef}
                                            id="logo"
                                            type="file"
                                            accept="image/jpeg,image/jpg,image/png,image/webp"
                                            className="hidden"
                                            onChange={(e) =>
                                                handleFileChange(
                                                    'logo',
                                                    e.target.files?.[0],
                                                    setLogoPreview,
                                                )
                                            }
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => logoRef.current?.click()}
                                        >
                                            <Upload className="mr-2 size-4" />
                                            {t('business.images.upload_logo')}
                                        </Button>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {t('business.images.logo_hint')}
                                        </p>
                                    </div>
                                </div>
                                <InputError message={errors.logo} />
                            </div>

                            {/* Banner */}
                            <div className="space-y-2">
                                <Label htmlFor="banner">{t('business.images.banner')}</Label>
                                <div className="flex flex-col gap-3">
                                    {bannerPreview && (
                                        <img
                                            src={bannerPreview}
                                            alt="Banner preview"
                                            className="h-32 w-full rounded-lg object-cover border"
                                        />
                                    )}
                                    <div>
                                        <input
                                            ref={bannerRef}
                                            id="banner"
                                            type="file"
                                            accept="image/jpeg,image/jpg,image/png,image/webp"
                                            className="hidden"
                                            onChange={(e) =>
                                                handleFileChange(
                                                    'banner',
                                                    e.target.files?.[0],
                                                    setBannerPreview,
                                                )
                                            }
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => bannerRef.current?.click()}
                                        >
                                            <Upload className="mr-2 size-4" />
                                            {t('business.images.upload_banner')}
                                        </Button>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {t('business.images.banner_hint')}
                                        </p>
                                    </div>
                                </div>
                                <InputError message={errors.banner} />
                            </div>

                            {/* Cover */}
                            <div className="space-y-2">
                                <Label htmlFor="cover">{t('business.images.cover')}</Label>
                                <div className="flex flex-col gap-3">
                                    {coverPreview && (
                                        <img
                                            src={coverPreview}
                                            alt="Cover preview"
                                            className="h-40 w-full rounded-lg object-cover border"
                                        />
                                    )}
                                    <div>
                                        <input
                                            ref={coverRef}
                                            id="cover"
                                            type="file"
                                            accept="image/jpeg,image/jpg,image/png,image/webp"
                                            className="hidden"
                                            onChange={(e) =>
                                                handleFileChange(
                                                    'cover',
                                                    e.target.files?.[0],
                                                    setCoverPreview,
                                                )
                                            }
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => coverRef.current?.click()}
                                        >
                                            <Upload className="mr-2 size-4" />
                                            {t('business.images.upload_cover')}
                                        </Button>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {t('business.images.cover_hint')}
                                        </p>
                                    </div>
                                </div>
                                <InputError message={errors.cover} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{t('business_profile.info_card_title')}</CardTitle>
                            <CardDescription>
                                {t('business_profile.info_card_desc')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">
                                    {t('business_profile.name_label')}{' '}
                                    <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="name"
                                    type="text"
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="description">
                                    {t('business_profile.description_label')}
                                </Label>
                                <textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) =>
                                        setData('description', e.target.value)
                                    }
                                    className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                    placeholder={t('business_profile.description_placeholder')}
                                />
                                <InputError message={errors.description} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{t('business_profile.contact_card_title')}</CardTitle>
                            <CardDescription>
                                {t('business_profile.contact_card_desc')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="email">{t('business_profile.email_label')}</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) =>
                                        setData('email', e.target.value)
                                    }
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="phone">{t('business_profile.phone_label')}</Label>
                                <Input
                                    id="phone"
                                    type="tel"
                                    value={data.phone}
                                    onChange={(e) =>
                                        setData('phone', e.target.value)
                                    }
                                />
                                <InputError message={errors.phone} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="address">{t('business_profile.address_label')}</Label>
                                <textarea
                                    id="address"
                                    value={data.address}
                                    onChange={(e) =>
                                        setData('address', e.target.value)
                                    }
                                    className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                    placeholder={t('business_profile.address_placeholder')}
                                />
                                <InputError message={errors.address} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{t('business_profile.loyalty_card_title')}</CardTitle>
                            <CardDescription>
                                {t('business_profile.loyalty_card_desc')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="loyalty_stamps_required">
                                    {t('business_profile.loyalty_stamps_label')}
                                </Label>
                                <Input
                                    id="loyalty_stamps_required"
                                    type="number"
                                    min="1"
                                    max="50"
                                    value={data.loyalty_stamps_required}
                                    onChange={(e) =>
                                        setData(
                                            'loyalty_stamps_required',
                                            parseInt(e.target.value, 10),
                                        )
                                    }
                                />
                                <p className="text-sm text-muted-foreground">
                                    {t('business_profile.loyalty_stamps_hint')}
                                </p>
                                <InputError
                                    message={errors.loyalty_stamps_required}
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="loyalty_reward_description">
                                    {t('business_profile.loyalty_reward_label')}
                                </Label>
                                <Input
                                    id="loyalty_reward_description"
                                    type="text"
                                    value={data.loyalty_reward_description}
                                    onChange={(e) =>
                                        setData(
                                            'loyalty_reward_description',
                                            e.target.value,
                                        )
                                    }
                                    placeholder={t('business_profile.loyalty_reward_placeholder')}
                                />
                                <p className="text-sm text-muted-foreground">
                                    {t('business_profile.loyalty_reward_hint')}
                                </p>
                                <InputError
                                    message={errors.loyalty_reward_description}
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{t('business.location.title')}</CardTitle>
                            <CardDescription>
                                {t('business.location.description')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="sector">
                                    {t('business.location.sector')}
                                </Label>
                                <Input
                                    id="sector"
                                    type="text"
                                    maxLength={80}
                                    value={data.sector}
                                    onChange={(e) =>
                                        setData('sector', e.target.value)
                                    }
                                    placeholder={t('business.location.sector_placeholder')}
                                />
                                <InputError message={errors.sector} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="province">
                                    {t('business.location.province')}
                                </Label>
                                <select
                                    id="province"
                                    value={data.province}
                                    onChange={(e) =>
                                        setData('province', e.target.value)
                                    }
                                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                >
                                    <option value="">{t('business.location.select_province')}</option>
                                    <option value="Distrito Nacional">Distrito Nacional</option>
                                    <option value="Santo Domingo">Santo Domingo</option>
                                    <option value="Santiago">Santiago</option>
                                    <option value="La Vega">La Vega</option>
                                    <option value="Puerto Plata">Puerto Plata</option>
                                    <option value="San Cristóbal">San Cristóbal</option>
                                    <option value="La Romana">La Romana</option>
                                    <option value="Duarte">Duarte</option>
                                    <option value="Espaillat">Espaillat</option>
                                    <option value="Otra">Otra</option>
                                </select>
                                <InputError message={errors.province} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="country">
                                    {t('business.location.country')}
                                </Label>
                                <select
                                    id="country"
                                    value={data.country}
                                    onChange={(e) =>
                                        setData('country', e.target.value)
                                    }
                                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                >
                                    <option value="DO">República Dominicana</option>
                                    <option value="US">Estados Unidos</option>
                                    <option value="PR">Puerto Rico</option>
                                </select>
                                <InputError message={errors.country} />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="latitude">
                                        {t('business.location.latitude')}
                                    </Label>
                                    <Input
                                        id="latitude"
                                        type="number"
                                        step="0.0000001"
                                        value={data.latitude ?? ''}
                                        onChange={(e) =>
                                            setData(
                                                'latitude',
                                                e.target.value
                                                    ? parseFloat(e.target.value)
                                                    : null,
                                            )
                                        }
                                    />
                                    <InputError message={errors.latitude} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="longitude">
                                        {t('business.location.longitude')}
                                    </Label>
                                    <Input
                                        id="longitude"
                                        type="number"
                                        step="0.0000001"
                                        value={data.longitude ?? ''}
                                        onChange={(e) =>
                                            setData(
                                                'longitude',
                                                e.target.value
                                                    ? parseFloat(e.target.value)
                                                    : null,
                                            )
                                        }
                                    />
                                    <InputError message={errors.longitude} />
                                </div>
                            </div>

                            <Button
                                type="button"
                                variant="outline"
                                onClick={handleGetLocation}
                            >
                                <MapPin className="mr-2 size-4" />
                                {t('business.location.use_current')}
                            </Button>
                        </CardContent>
                    </Card>

                    <div className="flex justify-end gap-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => window.history.back()}
                            disabled={processing}
                        >
                            <X className="mr-2 size-4" />
                            {t('common.cancel')}
                        </Button>
                        <Button type="submit" disabled={processing || !isDirty}>
                            <Save className="mr-2 size-4" />
                            {processing ? t('common.saving') : t('common.save_changes')}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
