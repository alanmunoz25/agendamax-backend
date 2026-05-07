import { FormEventHandler, useState, useRef } from 'react';
import { router, useForm } from '@inertiajs/react';
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
import type { ServiceCategory } from '@/types/models';
import { ArrowLeft, FolderTree, Upload, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface Props {
    category: ServiceCategory;
    parentCategories: ServiceCategory[];
}

export default function EditServiceCategory({ category, parentCategories }: Props) {
    const { t } = useTranslation();

    const { data, setData, post, processing, errors, isDirty } = useForm<{
        _method: string;
        name: string;
        description: string;
        image: File | null;
        parent_id: number | null;
        sort_order: number;
        is_active: boolean;
    }>({
        _method: 'put',
        name: category.name || '',
        description: category.description || '',
        image: null,
        parent_id: category.parent_id || null,
        sort_order: category.sort_order ?? 0,
        is_active: category.is_active ?? true,
    });

    const [imagePreview, setImagePreview] = useState<string | null>(
        category.image_url || null,
    );
    const fileInputRef = useRef<HTMLInputElement>(null);

    const handleImageChange = (file: File | null) => {
        setData('image', file);
        if (file) {
            const reader = new FileReader();
            reader.onload = (e) => setImagePreview(e.target?.result as string);
            reader.readAsDataURL(file);
        } else {
            setImagePreview(null);
        }
    };

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        const file = e.dataTransfer.files[0];
        if (file && file.type.startsWith('image/')) {
            handleImageChange(file);
        }
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`/service-categories/${category.id}`, {
            forceFormData: true,
        });
    };

    return (
        <AppLayout
            title={t('categories.edit_title')}
            breadcrumbs={[
                { label: t('breadcrumbs.dashboard'), href: '/dashboard' },
                { label: t('breadcrumbs.categories'), href: '/service-categories' },
                { label: category.name },
            ]}
        >
            <div className="mx-auto max-w-2xl space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight text-foreground">{t('categories.edit_title')}</h1>
                        <p className="mt-2 text-sm text-muted-foreground">{t('categories.edit_subtitle', { name: category.name })}</p>
                    </div>
                    <Button variant="outline" onClick={() => router.visit('/service-categories')}>
                        <ArrowLeft className="mr-2 h-4 w-4" />
                        {t('common.back')}
                    </Button>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    {/* Category Information */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <FolderTree className="h-5 w-5 text-muted-foreground" />
                                <CardTitle>{t('categories.info_card_title')}</CardTitle>
                            </div>
                            <CardDescription>{t('categories.info_card_desc')}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="name" required>
                                    {t('categories.name_label')}
                                </Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder={t('categories.name_placeholder')}
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="description">{t('categories.description_label')}</Label>
                                <Textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder={t('categories.description_placeholder')}
                                    rows={3}
                                />
                                <InputError message={errors.description} />
                            </div>

                            <div className="space-y-2">
                                <Label>{t('categories.parent_label')}</Label>
                                <Select
                                    value={data.parent_id ? String(data.parent_id) : 'none'}
                                    onValueChange={(value) =>
                                        setData('parent_id', value === 'none' ? null : Number(value))
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder={t('categories.parent_placeholder')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">{t('categories.no_parent')}</SelectItem>
                                        {parentCategories.map((cat) => (
                                            <SelectItem key={cat.id} value={String(cat.id)}>
                                                {cat.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.parent_id} />
                                <p className="text-xs text-muted-foreground">
                                    {t('categories.parent_edit_hint')}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Category Image */}
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('categories.image_card_title')}</CardTitle>
                            <CardDescription>{t('categories.image_card_desc')}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {imagePreview ? (
                                <div className="relative">
                                    <img
                                        src={imagePreview}
                                        alt="Preview"
                                        className="h-48 w-full rounded-lg border object-contain"
                                    />
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        size="sm"
                                        className="absolute right-2 top-2"
                                        onClick={() => handleImageChange(null)}
                                    >
                                        <X className="h-4 w-4" />
                                    </Button>
                                </div>
                            ) : (
                                <div
                                    className="flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-muted-foreground/25 p-12 transition-colors hover:border-muted-foreground/50"
                                    onClick={() => fileInputRef.current?.click()}
                                    onDragOver={(e) => e.preventDefault()}
                                    onDrop={handleDrop}
                                >
                                    <Upload className="mb-4 h-10 w-10 text-muted-foreground/50" />
                                    <p className="text-sm font-medium text-muted-foreground">
                                        {t('categories.image_upload_cta')}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground/75">
                                        {t('categories.image_upload_formats')}
                                    </p>
                                </div>
                            )}
                            <input
                                ref={fileInputRef}
                                type="file"
                                accept="image/jpeg,image/jpg,image/png,image/webp"
                                className="hidden"
                                onChange={(e) => handleImageChange(e.target.files?.[0] || null)}
                            />
                            <InputError message={errors.image} />
                        </CardContent>
                    </Card>

                    {/* Display Settings */}
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('categories.display_card_title')}</CardTitle>
                            <CardDescription>{t('categories.display_card_desc')}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="sort_order">{t('categories.sort_order_label')}</Label>
                                <Input
                                    id="sort_order"
                                    type="number"
                                    min="0"
                                    value={data.sort_order}
                                    onChange={(e) => setData('sort_order', parseInt(e.target.value) || 0)}
                                    placeholder="0"
                                />
                                <InputError message={errors.sort_order} />
                                <p className="text-xs text-muted-foreground">
                                    {t('categories.sort_order_hint')}
                                </p>
                            </div>

                            <div className="flex items-center justify-between">
                                <div className="space-y-0.5">
                                    <Label htmlFor="is_active">{t('categories.active_status_label')}</Label>
                                    <p className="text-sm text-muted-foreground">
                                        {t('categories.active_status_hint')}
                                    </p>
                                </div>
                                <Switch
                                    id="is_active"
                                    checked={data.is_active}
                                    onCheckedChange={(checked) => setData('is_active', checked)}
                                />
                            </div>
                            <InputError message={errors.is_active} />
                        </CardContent>
                    </Card>

                    {/* Actions */}
                    <div className="flex items-center justify-end gap-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => router.visit('/service-categories')}
                            disabled={processing}
                        >
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            {t('common.back')}
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
