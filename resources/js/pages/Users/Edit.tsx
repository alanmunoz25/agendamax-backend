import { FormEventHandler } from 'react';
import { useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
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
import type { User, Business } from '@/types/models';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Save, X } from 'lucide-react';
import { Head, usePage } from '@inertiajs/react';

interface Props {
    targetUser: User;
    businesses: Pick<Business, 'id' | 'name'>[];
    availableRoles: string[];
}

const roleLabels: Record<string, string> = {
    super_admin: 'Super Admin',
    business_admin: 'Business Admin',
    employee: 'Employee',
    client: 'Client',
};

export default function EditUser({ targetUser, businesses, availableRoles }: Props) {
    const { permissions } = usePage<SharedData>().props;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Users', href: '/users' },
        { title: targetUser.name, href: `/users/${targetUser.id}/edit` },
    ];

    const { data, setData, put, processing, errors, isDirty } = useForm({
        role: targetUser.role,
        business_id: targetUser.business_id ? String(targetUser.business_id) : '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(`/users/${targetUser.id}`, {
            data: {
                role: data.role,
                business_id: data.business_id || null,
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${targetUser.name}`} />
            <div className="mx-auto max-w-2xl space-y-6 p-4">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight text-foreground">Edit User</h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Update role and business assignment for {targetUser.name}
                    </p>
                </div>

                {/* User Info (read-only) */}
                <Card>
                    <CardHeader>
                        <CardTitle>User Information</CardTitle>
                        <CardDescription>Basic user details (read-only)</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <p className="text-xs font-medium text-muted-foreground">Name</p>
                                <p className="text-sm">{targetUser.name}</p>
                            </div>
                            <div>
                                <p className="text-xs font-medium text-muted-foreground">Email</p>
                                <p className="text-sm">{targetUser.email}</p>
                            </div>
                            <div>
                                <p className="text-xs font-medium text-muted-foreground">Phone</p>
                                <p className="text-sm">{targetUser.phone || 'Not set'}</p>
                            </div>
                            <div>
                                <p className="text-xs font-medium text-muted-foreground">Current Business</p>
                                <p className="text-sm">{targetUser.business?.name || 'None'}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <form onSubmit={submit} className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Role & Assignment</CardTitle>
                            <CardDescription>Change the user's role and business assignment</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label>Role</Label>
                                <Select value={data.role} onValueChange={(v) => setData('role', v as User['role'])}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {availableRoles.map((role) => (
                                            <SelectItem key={role} value={role}>
                                                {roleLabels[role] || role}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.role} />
                            </div>

                            {permissions.is_super_admin && (
                                <div className="space-y-2">
                                    <Label>Business</Label>
                                    <Select
                                        value={data.business_id || 'none'}
                                        onValueChange={(v) => setData('business_id', v === 'none' ? '' : v)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="No business" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">No business</SelectItem>
                                            {businesses.map((b) => (
                                                <SelectItem key={b.id} value={String(b.id)}>
                                                    {b.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.business_id} />
                                    <p className="text-xs text-muted-foreground">
                                        Assign the user to a business. Super admins typically have no business.
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <div className="flex items-center justify-end gap-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => window.history.back()}
                            disabled={processing}
                        >
                            <X className="mr-2 h-4 w-4" />
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing || !isDirty}>
                            <Save className="mr-2 h-4 w-4" />
                            {processing ? 'Saving...' : 'Save Changes'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
