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
import { type Business, type QrCode } from '@/types';
import { Head, Link } from '@inertiajs/react';
import {
    Building2,
    Edit,
    Mail,
    MapPin,
    Phone,
    QrCode as QrCodeIcon,
    Trophy,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface BusinessShowProps {
    business: Business;
    qrCodes?: QrCode[];
}

export default function Show({ business, qrCodes = [] }: BusinessShowProps) {
    const { t } = useTranslation();

    const breadcrumbs = [
        { title: t('breadcrumbs.dashboard'), href: '/dashboard' },
        { title: t('nav.business'), href: '/business' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('business_profile.title')} />

            <div className="flex flex-col gap-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">
                            {t('business_profile.title')}
                        </h1>
                        <p className="text-muted-foreground">
                            {t('business_profile.subtitle')}
                        </p>
                    </div>
                    <Link href="/business/edit">
                        <Button>
                            <Edit className="mr-2 size-4" />
                            {t('business_profile.edit_btn')}
                        </Button>
                    </Link>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Building2 className="size-5" />
                                {t('business_profile.info_card_title')}
                            </CardTitle>
                            <CardDescription>
                                {t('business_profile.info_card_desc')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">
                                    {t('business_profile.name_label')}
                                </label>
                                <p className="text-base">{business.name}</p>
                            </div>

                            {business.description && (
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">
                                        {t('business_profile.description_label')}
                                    </label>
                                    <p className="text-base">
                                        {business.description}
                                    </p>
                                </div>
                            )}

                            <div>
                                <label className="text-sm font-medium text-muted-foreground">
                                    {t('business_profile.invitation_code_label')}
                                </label>
                                <p className="font-mono text-base">
                                    {business.invitation_code}
                                </p>
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
                            {business.email && (
                                <div className="flex items-center gap-3">
                                    <Mail className="size-5 text-muted-foreground" />
                                    <div>
                                        <label className="text-sm font-medium text-muted-foreground">
                                            {t('business_profile.email_label')}
                                        </label>
                                        <p className="text-base">
                                            {business.email}
                                        </p>
                                    </div>
                                </div>
                            )}

                            {business.phone && (
                                <div className="flex items-center gap-3">
                                    <Phone className="size-5 text-muted-foreground" />
                                    <div>
                                        <label className="text-sm font-medium text-muted-foreground">
                                            {t('business_profile.phone_label')}
                                        </label>
                                        <p className="text-base">
                                            {business.phone}
                                        </p>
                                    </div>
                                </div>
                            )}

                            {business.address && (
                                <div className="flex items-center gap-3">
                                    <MapPin className="size-5 text-muted-foreground" />
                                    <div>
                                        <label className="text-sm font-medium text-muted-foreground">
                                            {t('business_profile.address_label')}
                                        </label>
                                        <p className="text-base">
                                            {business.address}
                                        </p>
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card className="md:col-span-2">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Trophy className="size-5" />
                                {t('business_profile.loyalty_card_title')}
                            </CardTitle>
                            <CardDescription>
                                {t('business_profile.loyalty_card_desc')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">
                                        {t('business_profile.loyalty_stamps_label')}
                                    </label>
                                    <p className="text-2xl font-bold">
                                        {business.loyalty_stamps_required}
                                    </p>
                                </div>

                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">
                                        {t('business_profile.loyalty_reward_label')}
                                    </label>
                                    <p className="text-base">
                                        {business.loyalty_reward_description ||
                                            t('business_profile.loyalty_not_set')}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="md:col-span-2">
                        <CardHeader className="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle className="flex items-center gap-2">
                                    <QrCodeIcon className="size-5" />
                                    {t('business_profile.qr_card_title')}
                                </CardTitle>
                                <CardDescription>
                                    {t('business_profile.qr_card_desc')}
                                </CardDescription>
                            </div>
                            <Link href="/qr-codes/create">
                                <Button>
                                    <QrCodeIcon className="mr-2 size-4" />
                                    {t('qr_codes.create_qr')}
                                </Button>
                            </Link>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {qrCodes.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('business_profile.no_qr_codes')}
                                </p>
                            ) : (
                                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    {qrCodes.map((qr) => (
                                        <div
                                            key={qr.id}
                                            className="rounded-lg border p-4 shadow-sm"
                                        >
                                            <div className="flex items-center justify-between">
                                                <Badge variant={qr.is_active ? 'default' : 'secondary'}>
                                                    {qr.is_active ? t('common.active') : t('common.inactive')}
                                                </Badge>
                                                <span className="text-xs text-muted-foreground">
                                                    {t('qr_codes.visit_qr')}
                                                </span>
                                            </div>

                                            <Separator className="my-3" />

                                            <div className="space-y-2">
                                                <div>
                                                    <p className="text-sm font-medium text-muted-foreground">
                                                        {t('qr_codes.reward_label')}
                                                    </p>
                                                    <p className="text-sm leading-snug">
                                                        {qr.reward_description}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p className="text-sm font-medium text-muted-foreground">
                                                        {t('qr_codes.stamps_required')}
                                                    </p>
                                                    <p className="text-lg font-semibold">
                                                        {qr.stamps_required}
                                                    </p>
                                                </div>
                                            </div>

                                            <div className="mt-3 flex items-center justify-between">
                                                <Link
                                                    href={`/qr-codes/${qr.id}`}
                                                    className="text-sm font-medium text-primary hover:underline"
                                                >
                                                    {t('qr_codes.details')}
                                                </Link>
                                                {qr.image_url && (
                                                    <Link
                                                        href={qr.image_url}
                                                        className="text-sm text-muted-foreground hover:underline"
                                                    >
                                                        {t('qr_codes.view_qr')}
                                                    </Link>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
