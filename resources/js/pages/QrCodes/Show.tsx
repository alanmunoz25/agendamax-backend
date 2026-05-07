import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { type QrCode } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { QrCode as QrCodeIcon } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface Props {
    qrCode: QrCode;
}

export default function Show({ qrCode }: Props) {
    const { t } = useTranslation();

    const breadcrumbs = [
        { title: t('breadcrumbs.dashboard'), href: '/dashboard' },
        { title: t('nav.business'), href: '/business' },
        { title: t('qr_codes.title'), href: '/qr-codes' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('qr_codes.show_title')} />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">{t('qr_codes.visit_qr')}</h1>
                    <p className="text-muted-foreground">
                        {t('qr_codes.show_subtitle')}
                    </p>
                </div>
                <Link href="/qr-codes">
                    <Button variant="outline">{t('common.back')}</Button>
                </Link>
            </div>

            <Card className="mt-6 max-w-3xl">
                <CardHeader className="flex items-center justify-between gap-4">
                    <div>
                        <CardTitle className="flex items-center gap-2">
                            <QrCodeIcon className="size-5" />
                            {t('qr_codes.visit_qr')}
                        </CardTitle>
                        <CardDescription>
                            {qrCode.reward_description}
                        </CardDescription>
                    </div>
                    <Badge variant={qrCode.is_active ? 'default' : 'secondary'}>
                        {qrCode.is_active ? t('common.active') : t('common.inactive')}
                    </Badge>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <p className="text-sm font-medium text-muted-foreground">{t('qr_codes.reward_label')}</p>
                            <p className="text-base">{qrCode.reward_description}</p>
                        </div>
                        <div>
                            <p className="text-sm font-medium text-muted-foreground">{t('qr_codes.stamps_required')}</p>
                            <p className="text-xl font-semibold">{qrCode.stamps_required}</p>
                        </div>
                    </div>

                    <Separator />

                    {qrCode.image_url ? (
                        <div className="space-y-2">
                            <p className="text-sm font-medium text-muted-foreground">{t('qr_codes.qr_image_label')}</p>
                            <div className="rounded-lg border p-4">
                                <img
                                    src={qrCode.image_url}
                                    alt="QR code"
                                    className="mx-auto h-64 w-64 object-contain"
                                />
                            </div>
                            <div className="flex gap-3">
                                <Link href={qrCode.image_url}>
                                    <Button variant="secondary">{t('qr_codes.download_qr')}</Button>
                                </Link>
                            </div>
                        </div>
                    ) : (
                        <p className="text-sm text-muted-foreground">{t('qr_codes.qr_image_not_available')}</p>
                    )}
                </CardContent>
            </Card>
        </AppLayout>
    );
}
