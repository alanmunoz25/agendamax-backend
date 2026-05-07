import { FormEventHandler, useMemo, useState } from 'react';
import { useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import InputError from '@/components/input-error';
import type { Service, ServiceCategory } from '@/types/models';
import { Briefcase } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface Props {
    service: Service;
    serviceCategories: ServiceCategory[];
}

export default function EditService({ service, serviceCategories }: Props) {
    const { t } = useTranslation();

    // Determine initial parent from the service's category relationship
    const initialParentId = service.service_category?.parent_id
        ? String(service.service_category.parent_id)
        : service.service_category?.parent
            ? String(service.service_category.parent.id)
            : '';

    const [selectedParentId, setSelectedParentId] = useState<string>(initialParentId);

    const { data, setData, put, processing, errors, isDirty } = useForm({
        name: service.name || '',
        description: service.description || '',
        duration: service.duration || 60,
        price: service.price || 0,
        category: service.category || '',
        service_category_id: service.service_category_id || null as number | null,
        is_active: service.is_active ?? true,
    });

    const subcategories = useMemo(() => {
        if (!selectedParentId) return [];
        const parent = serviceCategories.find((c) => c.id === Number(selectedParentId));
        return parent?.children || [];
    }, [selectedParentId, serviceCategories]);

    const handleParentChange = (value: string) => {
        setSelectedParentId(value === 'none' ? '' : value);
        setData((prev) => ({
            ...prev,
            service_category_id: null,
            category: value === 'none' ? '' : serviceCategories.find((c) => c.id === Number(value))?.name || '',
        }));
    };

    const handleSubcategoryChange = (value: string) => {
        setData('service_category_id', value === 'none' ? null : Number(value));
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(`/services/${service.id}`);
    };

    return (
        <AppLayout
            title={t('services.edit_title')}
            breadcrumbs={[
                { label: t('breadcrumbs.dashboard'), href: '/dashboard' },
                { label: t('breadcrumbs.services'), href: '/services' },
                { label: service.name, href: `/services/${service.id}` },
                { label: t('breadcrumbs.edit') },
            ]}
        >
            <div className="mx-auto max-w-2xl space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-3xl font-bold tracking-tight text-foreground">
                        {t('services.edit_title')}
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        {t('services.edit_subtitle', { name: service.name })}
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    {/* Basic Information */}
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
                            <div className="space-y-2">
                                <Label htmlFor="name" required>
                                    {t('services.name_label')}
                                </Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder={t('services.name_placeholder')}
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="description">{t('services.description_label')}</Label>
                                <Textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) =>
                                        setData('description', e.target.value)
                                    }
                                    placeholder={t('services.description_placeholder')}
                                    rows={3}
                                />
                                <InputError message={errors.description} />
                                <p className="text-xs text-muted-foreground">
                                    {t('services.description_hint')}
                                </p>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>{t('services.category_label')}</Label>
                                    <Select
                                        value={selectedParentId || 'none'}
                                        onValueChange={handleParentChange}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder={t('services.category_placeholder')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">{t('services.no_category')}</SelectItem>
                                            {serviceCategories.map((cat) => (
                                                <SelectItem key={cat.id} value={String(cat.id)}>
                                                    {cat.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.category} />
                                </div>

                                <div className="space-y-2">
                                    <Label>{t('services.subcategory_label')}</Label>
                                    <Select
                                        value={data.service_category_id ? String(data.service_category_id) : 'none'}
                                        onValueChange={handleSubcategoryChange}
                                        disabled={!selectedParentId || subcategories.length === 0}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder={t('services.subcategory_placeholder')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">{t('services.no_subcategory')}</SelectItem>
                                            {subcategories.map((sub) => (
                                                <SelectItem key={sub.id} value={String(sub.id)}>
                                                    {sub.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.service_category_id} />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Pricing & Duration */}
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('services.pricing_card_title')}</CardTitle>
                            <CardDescription>
                                {t('services.pricing_card_desc')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="price" required>
                                        {t('services.price_label_rd')}
                                    </Label>
                                    <Input
                                        id="price"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="999999.99"
                                        value={data.price}
                                        onChange={(e) =>
                                            setData('price', parseFloat(e.target.value))
                                        }
                                        placeholder="0.00"
                                        required
                                    />
                                    <InputError message={errors.price} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="duration" required>
                                        {t('services.duration_label_min')}
                                    </Label>
                                    <Input
                                        id="duration"
                                        type="number"
                                        min="15"
                                        max="480"
                                        step="15"
                                        value={data.duration}
                                        onChange={(e) =>
                                            setData('duration', parseInt(e.target.value))
                                        }
                                        placeholder="60"
                                        required
                                    />
                                    <InputError message={errors.duration} />
                                    <p className="text-xs text-muted-foreground">
                                        {t('services.duration_hint')}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Availability */}
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('services.availability_card_title')}</CardTitle>
                            <CardDescription>
                                {t('services.availability_card_desc')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center justify-between">
                                <div className="space-y-0.5">
                                    <Label htmlFor="is_active">{t('services.active_status_label')}</Label>
                                    <p className="text-sm text-muted-foreground">
                                        {t('services.active_status_hint')}
                                    </p>
                                </div>
                                <Switch
                                    id="is_active"
                                    checked={data.is_active}
                                    onCheckedChange={(checked) =>
                                        setData('is_active', checked)
                                    }
                                />
                            </div>
                            <InputError message={errors.is_active} className="mt-2" />
                        </CardContent>
                    </Card>

                    {/* Actions */}
                    <div className="flex items-center justify-end gap-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => window.history.back()}
                            disabled={processing}
                        >
                            {t('common.cancel')}
                        </Button>
                        <Button type="submit" disabled={processing || !isDirty}>
                            {processing ? t('common.saving') : t('common.save_changes')}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
