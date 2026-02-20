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
import { type BreadcrumbItem, type Business, type QrCode } from '@/types';
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

interface BusinessShowProps {
    business: Business;
    qrCodes?: QrCode[];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Business',
        href: '/business',
    },
];

export default function Show({ business, qrCodes = [] }: BusinessShowProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Business Profile" />

            <div className="flex flex-col gap-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">
                            Business Profile
                        </h1>
                        <p className="text-muted-foreground">
                            Manage your business information and settings
                        </p>
                    </div>
                    <Link href="/business/edit">
                        <Button>
                            <Edit className="mr-2 size-4" />
                            Edit Profile
                        </Button>
                    </Link>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Building2 className="size-5" />
                                Business Information
                            </CardTitle>
                            <CardDescription>
                                General business details
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">
                                    Business Name
                                </label>
                                <p className="text-base">{business.name}</p>
                            </div>

                            {business.description && (
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">
                                        Description
                                    </label>
                                    <p className="text-base">
                                        {business.description}
                                    </p>
                                </div>
                            )}

                            <div>
                                <label className="text-sm font-medium text-muted-foreground">
                                    Invitation Code
                                </label>
                                <p className="font-mono text-base">
                                    {business.invitation_code}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Contact Information</CardTitle>
                            <CardDescription>
                                How clients can reach you
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {business.email && (
                                <div className="flex items-center gap-3">
                                    <Mail className="size-5 text-muted-foreground" />
                                    <div>
                                        <label className="text-sm font-medium text-muted-foreground">
                                            Email
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
                                            Phone
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
                                            Address
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
                                Loyalty Program
                            </CardTitle>
                            <CardDescription>
                                Reward your loyal customers
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">
                                        Stamps Required for Reward
                                    </label>
                                    <p className="text-2xl font-bold">
                                        {business.loyalty_stamps_required}
                                    </p>
                                </div>

                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">
                                        Reward Description
                                    </label>
                                    <p className="text-base">
                                        {business.loyalty_reward_description ||
                                            'No reward description set'}
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
                                    QR Codes
                                </CardTitle>
                                <CardDescription>
                                    Visit QR codes for rewards and stamp tracking
                                </CardDescription>
                            </div>
                            <Link href="/qr-codes/create">
                                <Button>
                                    <QrCodeIcon className="mr-2 size-4" />
                                    Create QR
                                </Button>
                            </Link>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {qrCodes.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No QR codes yet. Create one to share with clients.
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
                                                    {qr.is_active ? 'Active' : 'Inactive'}
                                                </Badge>
                                                <span className="text-xs text-muted-foreground">
                                                    Visit QR
                                                </span>
                                            </div>

                                            <Separator className="my-3" />

                                            <div className="space-y-2">
                                                <div>
                                                    <p className="text-sm font-medium text-muted-foreground">
                                                        Reward
                                                    </p>
                                                    <p className="text-sm leading-snug">
                                                        {qr.reward_description}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p className="text-sm font-medium text-muted-foreground">
                                                        Stamps Required
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
                                                    Details
                                                </Link>
                                                {qr.image_url && (
                                                    <Link
                                                        href={qr.image_url}
                                                        className="text-sm text-muted-foreground hover:underline"
                                                    >
                                                        View QR
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
