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
import { Building2 } from 'lucide-react';
import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

export default function CreateBusiness() {
    const { t } = useTranslation();

    const breadcrumbs = [
        { title: t('breadcrumbs.dashboard'), href: '/dashboard' },
        { title: t('breadcrumbs.businesses'), href: '/businesses' },
        { title: t('breadcrumbs.create'), href: '/businesses/create' },
    ];

    const { data, setData, post, processing, errors, isDirty } = useForm({
        name: '',
        slug: '',
        description: '',
        email: '',
        phone: '',
        address: '',
        status: 'active',
        timezone: 'America/Mexico_City',
        loyalty_stamps_required: 10,
        loyalty_reward_description: '',
    });

    const handleNameChange = (value: string) => {
        setData((prev) => ({
            ...prev,
            name: value,
            slug: value
                .toLowerCase()
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/\s+/g, '-'),
        }));
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/businesses');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('businesses.create_title')} />
            <div className="mx-auto max-w-2xl space-y-6 p-4">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight text-foreground">{t('businesses.create_title')}</h1>
                    <p className="mt-2 text-sm text-muted-foreground">{t('businesses.create_subtitle')}</p>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Building2 className="h-5 w-5 text-muted-foreground" />
                                <CardTitle>{t('businesses.info_card_title')}</CardTitle>
                            </div>
                            <CardDescription>{t('businesses.info_card_desc')}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">{t('businesses.name_label')}</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => handleNameChange(e.target.value)}
                                    placeholder="ej: Luxe Beauty Salon"
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
                                    placeholder="ej: luxe-beauty-salon"
                                    required
                                />
                                <InputError message={errors.slug} />
                                <p className="text-xs text-muted-foreground">
                                    {t('businesses.slug_hint')}
                                </p>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="description">{t('businesses.description_label')}</Label>
                                <Textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder={t('businesses.description_placeholder')}
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
                                        placeholder="contacto@negocio.com"
                                        required
                                    />
                                    <InputError message={errors.email} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="phone">{t('businesses.phone_label')}</Label>
                                    <Input
                                        id="phone"
                                        value={data.phone}
                                        onChange={(e) => setData('phone', e.target.value)}
                                        placeholder="+1 (809) 555-1234"
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
                                    placeholder="Calle 123, Ciudad, Estado"
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
                                        placeholder="America/Santo_Domingo"
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
                                        placeholder={t('businesses.loyalty_reward_placeholder')}
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
                            {t('common.cancel')}
                        </Button>
                        <Button type="submit" disabled={processing || !isDirty}>
                            {processing ? t('common.creating') : t('businesses.create_title')}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
