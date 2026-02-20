import { FormEventHandler } from 'react';
import { useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import InputError from '@/components/input-error';
import { UserPlus, Save, X } from 'lucide-react';
import { Link } from '@inertiajs/react';

export default function CreateClient() {
    const { data, setData, post, processing, errors, isDirty } = useForm({
        name: '',
        email: '',
        phone: '',
        avatar_url: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/clients');
    };

    return (
        <AppLayout
            title="Add Client"
            breadcrumbs={[
                { label: 'Dashboard', href: '/dashboard' },
                { label: 'Clients', href: '/clients' },
                { label: 'Add New' },
            ]}
        >
            <div className="mx-auto max-w-2xl space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-3xl font-bold tracking-tight text-foreground flex items-center gap-2">
                        <UserPlus className="h-8 w-8" />
                        Add New Client
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Manually add a new client to your business
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Client Information</CardTitle>
                            <CardDescription>
                                Enter the client's contact details. They will receive a verification email
                                to set their password.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {/* Name */}
                            <div>
                                <Label htmlFor="name">Full Name *</Label>
                                <Input
                                    id="name"
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="John Doe"
                                    className="mt-1"
                                    autoFocus
                                />
                                <InputError message={errors.name} className="mt-2" />
                            </div>

                            {/* Email */}
                            <div>
                                <Label htmlFor="email">Email Address *</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    placeholder="john@example.com"
                                    className="mt-1"
                                />
                                <InputError message={errors.email} className="mt-2" />
                            </div>

                            {/* Phone */}
                            <div>
                                <Label htmlFor="phone">Phone Number</Label>
                                <Input
                                    id="phone"
                                    type="tel"
                                    value={data.phone}
                                    onChange={(e) => setData('phone', e.target.value)}
                                    placeholder="+1 (555) 123-4567"
                                    className="mt-1"
                                />
                                <InputError message={errors.phone} className="mt-2" />
                            </div>

                            {/* Avatar URL */}
                            <div>
                                <Label htmlFor="avatar_url">Avatar URL (Optional)</Label>
                                <Input
                                    id="avatar_url"
                                    type="url"
                                    value={data.avatar_url}
                                    onChange={(e) => setData('avatar_url', e.target.value)}
                                    placeholder="https://example.com/avatar.jpg"
                                    className="mt-1"
                                />
                                <InputError message={errors.avatar_url} className="mt-2" />
                            </div>
                        </CardContent>
                    </Card>

                    {/* Actions */}
                    <div className="flex items-center justify-between gap-4">
                        <Link
                            href="/clients"
                            className="inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:bg-accent hover:text-accent-foreground h-10 px-4 py-2"
                        >
                            <X className="mr-2 h-4 w-4" />
                            Cancel
                        </Link>
                        <Button type="submit" disabled={processing || !isDirty}>
                            <Save className="mr-2 h-4 w-4" />
                            {processing ? 'Creating...' : 'Create Client'}
                        </Button>
                    </div>
                </form>

                {/* Help Card */}
                <Card className="bg-muted/50">
                    <CardHeader>
                        <CardTitle className="text-base">Note</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm text-muted-foreground">
                        <p>
                            Clients added manually will receive an email to verify their account and set
                            a password. They can then log in to view their appointments and loyalty
                            rewards.
                        </p>
                        <p>
                            Make sure the email address is correct, as this will be used for all
                            communications.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
