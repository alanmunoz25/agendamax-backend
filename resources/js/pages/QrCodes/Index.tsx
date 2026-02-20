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
    qrCodes: QrCode[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Business', href: '/business' },
    { title: 'QR Codes', href: '/qr-codes' },
];

export default function Index({ qrCodes }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="QR Codes" />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">QR Codes</h1>
                    <p className="text-muted-foreground">
                        Manage visit QR codes for rewards and stamp tracking.
                    </p>
                </div>
                <Link href="/qr-codes/create">
                    <Button>
                        <QrCodeIcon className="mr-2 size-4" />
                        Create QR
                    </Button>
                </Link>
            </div>

            <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {qrCodes.length === 0 && (
                    <Card className="sm:col-span-2 lg:col-span-3">
                        <CardHeader>
                            <CardTitle>No QR codes yet</CardTitle>
                            <CardDescription>
                                Create a QR to share with clients.
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
                                    Visit QR
                                </CardTitle>
                                <CardDescription>{qr.reward_description}</CardDescription>
                            </div>
                            <Badge variant={qr.is_active ? 'default' : 'secondary'}>
                                {qr.is_active ? 'Active' : 'Inactive'}
                            </Badge>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="flex items-center justify-between text-sm">
                                <span className="text-muted-foreground">Stamps Required</span>
                                <span className="font-semibold">{qr.stamps_required}</span>
                            </div>
                            <Separator />
                            <div className="flex items-center justify-between text-sm">
                                <Link
                                    href={`/qr-codes/${qr.id}`}
                                    className="font-medium text-primary hover:underline"
                                >
                                    Details
                                </Link>
                                {qr.image_url && (
                                    <Link
                                        href={qr.image_url}
                                        className="text-muted-foreground hover:underline"
                                    >
                                        View QR
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
