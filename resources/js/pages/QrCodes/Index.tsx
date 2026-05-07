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
    qrCodes: QrCode[];
}

export default function Index({ qrCodes }: Props) {
    const { t } = useTranslation();

    const breadcrumbs = [
        { title: t('breadcrumbs.dashboard'), href: '/dashboard' },
        { title: t('nav.business'), href: '/business' },
        { title: t('qr_codes.title'), href: '/qr-codes' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('qr_codes.title')} />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">{t('qr_codes.title')}</h1>
                    <p className="text-muted-foreground">
                        {t('qr_codes.empty_description')}
                    </p>
                </div>
                <Link href="/qr-codes/create">
                    <Button>
                        <QrCodeIcon className="mr-2 size-4" />
                        {t('qr_codes.create_qr')}
                    </Button>
                </Link>
            </div>

            <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {qrCodes.length === 0 && (
                    <Card className="sm:col-span-2 lg:col-span-3">
                        <CardHeader>
                            <CardTitle>{t('qr_codes.no_qr_yet_title')}</CardTitle>
                            <CardDescription>
                                {t('qr_codes.no_qr_yet_desc')}
                            </CardDescription>
                        </CardHeader>
                    </Card>
                )}

                {qrCodes.map((qr) => (
                    <Card key={qr.id}>
                        <CardHeader className="flex items-center justify-between">
                            <div>
                                <CardTitle className="flex items-center gap-2">
                                    <QrCodeIcon className="size-5" />
                                    {t('qr_codes.visit_qr')}
                                </CardTitle>
                                <CardDescription>{qr.reward_description}</CardDescription>
                            </div>
                            <Badge variant={qr.is_active ? 'default' : 'secondary'}>
                                {qr.is_active ? t('common.active') : t('common.inactive')}
                            </Badge>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="flex items-center justify-between text-sm">
                                <span className="text-muted-foreground">{t('qr_codes.stamps_required')}</span>
                                <span className="font-semibold">{qr.stamps_required}</span>
                            </div>
                            <Separator />
                            <div className="flex items-center justify-between text-sm">
                                <Link
                                    href={`/qr-codes/${qr.id}`}
                                    className="font-medium text-primary hover:underline"
                                >
                                    {t('qr_codes.details')}
                                </Link>
                                {qr.image_url && (
                                    <Link
                                        href={qr.image_url}
                                        className="text-muted-foreground hover:underline"
                                    >
                                        {t('qr_codes.view_qr')}
                                    </Link>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>
        </AppLayout>
    );
}
