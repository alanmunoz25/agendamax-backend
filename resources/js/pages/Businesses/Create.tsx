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
import { type BreadcrumbItem } from '@/types';
import { Building2 } from 'lucide-react';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Businesses', href: '/businesses' },
    { title: 'Create', href: '/businesses/create' },
];

export default function CreateBusiness() {
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
            <Head title="Create Business" />
            <div className="mx-auto max-w-2xl space-y-6 p-4">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight text-foreground">Create Business</h1>
                    <p className="mt-2 text-sm text-muted-foreground">Add a new business to the platform</p>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Building2 className="h-5 w-5 text-muted-foreground" />
                                <CardTitle>Business Information</CardTitle>
                            </div>
                            <CardDescription>Basic details about the business</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">Business Name</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => handleNameChange(e.target.value)}
                                    placeholder="e.g., Luxe Beauty Salon"
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="slug">URL Slug</Label>
                                <Input
                                    id="slug"
                                    value={data.slug}
                                    onChange={(e) => setData('slug', e.target.value)}
                                    placeholder="e.g., luxe-beauty-salon"
                                    required
                                />
                                <InputError message={errors.slug} />
                                <p className="text-xs text-muted-foreground">
                                    Used for the business URL. Auto-generated from name.
                                </p>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="description">Description</Label>
                                <Textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder="Describe the business..."
                                    rows={3}
                                />
                                <InputError message={errors.description} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="email">Email</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        placeholder="contact@business.com"
                                        required
                                    />
                                    <InputError message={errors.email} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="phone">Phone</Label>
                                    <Input
                                        id="phone"
                                        value={data.phone}
                                        onChange={(e) => setData('phone', e.target.value)}
                                        placeholder="+1 (555) 123-4567"
                                    />
                                    <InputError message={errors.phone} />
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="address">Address</Label>
                                <Input
                                    id="address"
                                    value={data.address}
                                    onChange={(e) => setData('address', e.target.value)}
                                    placeholder="123 Main St, City, State"
                                />
                                <InputError message={errors.address} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Settings</CardTitle>
                            <CardDescription>Business status and configuration</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>Status</Label>
                                    <Select value={data.status} onValueChange={(v) => setData('status', v)}>
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="active">Active</SelectItem>
                                            <SelectItem value="inactive">Inactive</SelectItem>
                                            <SelectItem value="suspended">Suspended</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.status} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="timezone">Timezone</Label>
                                    <Input
                                        id="timezone"
                                        value={data.timezone}
                                        onChange={(e) => setData('timezone', e.target.value)}
                                        placeholder="America/Mexico_City"
                                    />
                                    <InputError message={errors.timezone} />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Loyalty Program</CardTitle>
                            <CardDescription>Configure the loyalty stamp system</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="loyalty_stamps_required">Stamps Required</Label>
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
                                    <Label htmlFor="loyalty_reward_description">Reward Description</Label>
                                    <Input
                                        id="loyalty_reward_description"
                                        value={data.loyalty_reward_description}
                                        onChange={(e) => setData('loyalty_reward_description', e.target.value)}
                                        placeholder="e.g., Free haircut after 10 visits"
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
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing || !isDirty}>
                            {processing ? 'Creating...' : 'Create Business'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
