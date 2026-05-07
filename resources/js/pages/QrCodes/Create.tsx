import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { QrCode as QrCodeIcon } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export default function Create() {
    const { t } = useTranslation();

    const breadcrumbs = [
        { title: t('breadcrumbs.dashboard'), href: '/dashboard' },
        { title: t('nav.business'), href: '/business' },
        { title: t('qr_codes.title'), href: '/qr-codes' },
        { title: t('breadcrumbs.create'), href: '/qr-codes/create' },
    ];

    const { data, setData, post, processing, errors } = useForm({
        reward_description: '',
        stamps_required: 1,
        is_active: true,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/qr-codes');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('qr_codes.create_title')} />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">{t('qr_codes.create_title')}</h1>
                    <p className="text-muted-foreground">
                        {t('qr_codes.create_subtitle')}
                    </p>
                </div>
                <Link href="/qr-codes">
                    <Button variant="outline">{t('qr_codes.back_to_list')}</Button>
                </Link>
            </div>

            <Card className="mt-6 max-w-2xl">
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <QrCodeIcon className="size-5" />
                        {t('qr_codes.qr_details_card_title')}
                    </CardTitle>
                    <CardDescription>{t('qr_codes.qr_details_card_desc')}</CardDescription>
                </CardHeader>
                <CardContent>
                    <form className="space-y-6" onSubmit={submit}>
                        <div className="space-y-2">
                            <Label htmlFor="reward_description">{t('qr_codes.reward_description_label')}</Label>
                            <Input
                                id="reward_description"
                                value={data.reward_description}
                                onChange={(e) => setData('reward_description', e.target.value)}
                                placeholder={t('qr_codes.reward_placeholder')}
                            />
                            {errors.reward_description && (
                                <p className="text-sm text-destructive">{errors.reward_description}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="stamps_required">{t('qr_codes.stamps_required')}</Label>
                            <Input
                                id="stamps_required"
                                type="number"
                                min={1}
                                value={data.stamps_required}
                                onChange={(e) => setData('stamps_required', Number(e.target.value))}
                            />
                            {errors.stamps_required && (
                                <p className="text-sm text-destructive">{errors.stamps_required}</p>
                            )}
                        </div>

                        <div className="flex items-center justify-between rounded-lg border p-3">
                            <div>
                                <Label htmlFor="is_active">{t('qr_codes.active_label')}</Label>
                                <p className="text-sm text-muted-foreground">
                                    {t('qr_codes.active_hint')}
                                </p>
                            </div>
                            <Switch
                                id="is_active"
                                checked={data.is_active}
                                onCheckedChange={(checked) => setData('is_active', checked)}
                            />
                        </div>

                        {errors.is_active && (
                            <p className="text-sm text-destructive">{errors.is_active}</p>
                        )}

                        <Button type="submit" disabled={processing}>
                            {processing ? t('common.creating') : t('qr_codes.create_qr')}
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </AppLayout>
    );
}
