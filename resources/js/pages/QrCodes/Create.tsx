import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { QrCode as QrCodeIcon } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Business', href: '/business' },
    { title: 'QR Codes', href: '/qr-codes' },
    { title: 'Create', href: '/qr-codes/create' },
];

export default function Create() {
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
            <Head title="Create QR" />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">Create QR</h1>
                    <p className="text-muted-foreground">
                        Generate a visit QR code with reward and stamp requirements.
                    </p>
                </div>
                <Link href="/qr-codes">
                    <Button variant="outline">Back to list</Button>
                </Link>
            </div>

            <Card className="mt-6 max-w-2xl">
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <QrCodeIcon className="size-5" />
                        QR Details
                    </CardTitle>
                    <CardDescription>Define the reward and stamps needed.</CardDescription>
                </CardHeader>
                <CardContent>
                    <form className="space-y-6" onSubmit={submit}>
                        <div className="space-y-2">
                            <Label htmlFor="reward_description">Reward Description</Label>
                            <Input
                                id="reward_description"
                                value={data.reward_description}
                                onChange={(e) => setData('reward_description', e.target.value)}
                                placeholder="e.g., Free coffee after 5 visits"
                            />
                            {errors.reward_description && (
                                <p className="text-sm text-destructive">{errors.reward_description}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="stamps_required">Stamps Required</Label>
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
                                <Label htmlFor="is_active">Active</Label>
                                <p className="text-sm text-muted-foreground">
                                    Control if this QR can be scanned right now.
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
                            Create QR
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </AppLayout>
    );
}
