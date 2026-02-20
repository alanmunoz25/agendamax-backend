import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Business } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Save, X } from 'lucide-react';
import { type FormEventHandler } from 'react';

interface BusinessEditProps {
    business: Business;
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
    {
        title: 'Edit',
        href: '/business/edit',
    },
];

export default function Edit({ business }: BusinessEditProps) {
    const { data, setData, put, processing, errors, isDirty } = useForm({
        name: business.name || '',
        description: business.description || '',
        email: business.email || '',
        phone: business.phone || '',
        address: business.address || '',
        loyalty_stamps_required: business.loyalty_stamps_required || 10,
        loyalty_reward_description:
            business.loyalty_reward_description || '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put('/business');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit Business Profile" />

            <div className="flex flex-col gap-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">
                            Edit Business Profile
                        </h1>
                        <p className="text-muted-foreground">
                            Update your business information and settings
                        </p>
                    </div>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Business Information</CardTitle>
                            <CardDescription>
                                General details about your business
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">
                                    Business Name{' '}
                                    <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="name"
                                    type="text"
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="description">
                                    Description
                                </Label>
                                <textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) =>
                                        setData('description', e.target.value)
                                    }
                                    className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                    placeholder="Brief description of your business"
                                />
                                <InputError message={errors.description} />
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
                            <div className="space-y-2">
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) =>
                                        setData('email', e.target.value)
                                    }
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="phone">Phone</Label>
                                <Input
                                    id="phone"
                                    type="tel"
                                    value={data.phone}
                                    onChange={(e) =>
                                        setData('phone', e.target.value)
                                    }
                                />
                                <InputError message={errors.phone} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="address">Address</Label>
                                <textarea
                                    id="address"
                                    value={data.address}
                                    onChange={(e) =>
                                        setData('address', e.target.value)
                                    }
                                    className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                    placeholder="Business address"
                                />
                                <InputError message={errors.address} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Loyalty Program Settings</CardTitle>
                            <CardDescription>
                                Configure your customer loyalty rewards
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="loyalty_stamps_required">
                                    Stamps Required for Reward
                                </Label>
                                <Input
                                    id="loyalty_stamps_required"
                                    type="number"
                                    min="1"
                                    max="50"
                                    value={data.loyalty_stamps_required}
                                    onChange={(e) =>
                                        setData(
                                            'loyalty_stamps_required',
                                            parseInt(e.target.value, 10),
                                        )
                                    }
                                />
                                <p className="text-sm text-muted-foreground">
                                    Number of stamps clients need to earn a
                                    reward (1-50)
                                </p>
                                <InputError
                                    message={errors.loyalty_stamps_required}
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="loyalty_reward_description">
                                    Reward Description
                                </Label>
                                <Input
                                    id="loyalty_reward_description"
                                    type="text"
                                    value={data.loyalty_reward_description}
                                    onChange={(e) =>
                                        setData(
                                            'loyalty_reward_description',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="e.g., Free haircut, 50% off next visit"
                                />
                                <p className="text-sm text-muted-foreground">
                                    What reward do clients get when they
                                    complete their stamps?
                                </p>
                                <InputError
                                    message={errors.loyalty_reward_description}
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex justify-end gap-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => window.history.back()}
                            disabled={processing}
                        >
                            <X className="mr-2 size-4" />
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing || !isDirty}>
                            <Save className="mr-2 size-4" />
                            {processing ? 'Saving...' : 'Save Changes'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
