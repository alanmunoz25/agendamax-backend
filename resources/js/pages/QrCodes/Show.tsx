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
import { type BreadcrumbItem, type QrCode } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { QrCode as QrCodeIcon } from 'lucide-react';

interface Props {
    qrCode: QrCode;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Business', href: '/business' },
    { title: 'QR Codes', href: '/qr-codes' },
];

export default function Show({ qrCode }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="QR Code Details" />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">QR Code</h1>
                    <p className="text-muted-foreground">
                        Visit QR details and download link.
                    </p>
                </div>
                <Link href="/qr-codes">
                    <Button variant="outline">Back</Button>
                </Link>
            </div>

            <Card className="mt-6 max-w-3xl">
                <CardHeader className="flex items-center justify-between gap-4">
                    <div>
                        <CardTitle className="flex items-center gap-2">
                            <QrCodeIcon className="size-5" />
                            Visit QR
                        </CardTitle>
                        <CardDescription>
                            {qrCode.reward_description}
                        </CardDescription>
                    </div>
                    <Badge variant={qrCode.is_active ? 'default' : 'secondary'}>
                        {qrCode.is_active ? 'Active' : 'Inactive'}
                    </Badge>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <p className="text-sm font-medium text-muted-foreground">Reward</p>
                            <p className="text-base">{qrCode.reward_description}</p>
                        </div>
                        <div>
                            <p className="text-sm font-medium text-muted-foreground">Stamps Required</p>
                            <p className="text-xl font-semibold">{qrCode.stamps_required}</p>
                        </div>
                    </div>

                    <Separator />

                    {qrCode.image_url ? (
                        <div className="space-y-2">
                            <p className="text-sm font-medium text-muted-foreground">QR Image</p>
                            <div className="rounded-lg border p-4">
                                <img
                                    src={qrCode.image_url}
                                    alt="QR code"
                                    className="mx-auto h-64 w-64 object-contain"
                                />
                            </div>
                            <div className="flex gap-3">
                                <Link href={qrCode.image_url}>
                                    <Button variant="secondary">Download QR</Button>
                                </Link>
                            </div>
                        </div>
                    ) : (
                        <p className="text-sm text-muted-foreground">QR image not available.</p>
                    )}
                </CardContent>
            </Card>
        </AppLayout>
    );
}
