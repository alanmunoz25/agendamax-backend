import { FormEventHandler } from 'react';
import { useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
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
import type { Business } from '@/types/models';
import { Save, X } from 'lucide-react';
import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

interface Props {
    business: Business;
}

export default function EditBusiness({ business }: Props) {
    const { t } = useTranslation();

    const breadcrumbs = [
        { title: t('breadcrumbs.dashboard'), href: '/dashboard' },
        { title: t('breadcrumbs.businesses'), href: '/businesses' },
        { title: business.name, href: `/businesses/${business.id}` },
        { title: t('breadcrumbs.edit'), href: `/businesses/${business.id}/edit` },
    ];

    const { data, setData, put, processing, errors, isDirty } = useForm({
        name: business.name || '',
        slug: business.slug || '',
        description: business.description || '',
        email: business.email || '',
        phone: business.phone || '',
        address: business.address || '',
        status: business.status || 'active',
        timezone: business.timezone || 'America/Mexico_City',
        loyalty_stamps_required: business.loyalty_stamps_required || 10,
        loyalty_reward_description: business.loyalty_reward_description || '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(`/businesses/${business.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${t('businesses.edit_title')} - ${business.name}`} />
            <div className="mx-auto max-w-2xl space-y-6 p-4">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight text-foreground">{t('businesses.edit_title')}</h1>
                    <p className="mt-2 text-sm text-muted-foreground">{t('businesses.edit_subtitle')}</p>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('businesses.info_card_title')}</CardTitle>
                            <CardDescription>{t('businesses.info_card_edit_desc')}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">{t('businesses.name_label')}</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="slug">{t('businesses.slug_label')}</Label>
                                <Input
                                    id="slug"
                                    value={data.slug}
                                    onChange={(e) => setData('slug', e.target.value)}
                                    required
                                />
                                <InputError message={errors.slug} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="description">{t('businesses.description_label')}</Label>
                                <Textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    rows={3}
                                />
                                <InputError message={errors.description} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="email">{t('businesses.email_label')}</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                    />
                                    <InputError message={errors.email} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="phone">{t('businesses.phone_label')}</Label>
                                    <Input
                                        id="phone"
                                        value={data.phone}
                                        onChange={(e) => setData('phone', e.target.value)}
                                    />
                                    <InputError message={errors.phone} />
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="address">{t('businesses.address_label')}</Label>
                                <Input
                                    id="address"
                                    value={data.address}
                                    onChange={(e) => setData('address', e.target.value)}
                                />
                                <InputError message={errors.address} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{t('businesses.settings_card_title')}</CardTitle>
                            <CardDescription>{t('businesses.settings_card_desc')}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>{t('common.status')}</Label>
                                    <Select value={data.status} onValueChange={(v) => setData('status', v)}>
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="active">{t('businesses.status_active')}</SelectItem>
                                            <SelectItem value="inactive">{t('businesses.status_inactive')}</SelectItem>
                                            <SelectItem value="suspended">{t('businesses.status_suspended')}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.status} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="timezone">{t('businesses.timezone_label')}</Label>
                                    <Input
                                        id="timezone"
                                        value={data.timezone}
                                        onChange={(e) => setData('timezone', e.target.value)}
                                    />
                                    <InputError message={errors.timezone} />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{t('businesses.loyalty_card_title')}</CardTitle>
                            <CardDescription>{t('businesses.loyalty_card_desc')}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="loyalty_stamps_required">{t('businesses.loyalty_stamps_label')}</Label>
                                    <Input
                                        id="loyalty_stamps_required"
                                        type="number"
                                        min="1"
                                        max="50"
                                        value={data.loyalty_stamps_required}
                                        onChange={(e) =>
                                            setData('loyalty_stamps_required', parseInt(e.target.value) || 10)
                                        }
                                    />
                                    <InputError message={errors.loyalty_stamps_required} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="loyalty_reward_description">{t('businesses.loyalty_reward_label')}</Label>
                                    <Input
                                        id="loyalty_reward_description"
                                        value={data.loyalty_reward_description}
                                        onChange={(e) => setData('loyalty_reward_description', e.target.value)}
                                    />
                                    <InputError message={errors.loyalty_reward_description} />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex items-center justify-end gap-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => window.history.back()}
                            disabled={processing}
                        >
                            <X className="mr-2 h-4 w-4" />
                            {t('common.cancel')}
                        </Button>
                        <Button type="submit" disabled={processing || !isDirty}>
                            <Save className="mr-2 h-4 w-4" />
                            {processing ? t('common.saving') : t('common.save_changes')}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
