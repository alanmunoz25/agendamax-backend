import { useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { ConfirmationModal } from '@/components/confirmation-modal';
import type { Employee } from '@/types/models';
import { Edit, Trash2, Users, Briefcase, Mail, User } from 'lucide-react';

interface Props {
    employee: Employee;
}

export default function ShowEmployee({ employee }: Props) {
    const [showDeleteModal, setShowDeleteModal] = useState(false);

    const handleDelete = () => {
        router.delete(`/employees/${employee.id}`, {
            onSuccess: () => router.visit('/employees'),
        });
    };

    return (
        <AppLayout
            title={employee.user?.name || 'Employee'}
            breadcrumbs={[
                { label: 'Dashboard', href: '/dashboard' },
                { label: 'Employees', href: '/employees' },
                { label: employee.user?.name || 'Employee' },
            ]}
        >
            <div className="mx-auto max-w-4xl space-y-6">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div className="flex items-center gap-4">
                        {employee.photo_url ? (
                            <img
                                src={employee.photo_url}
                                alt={employee.user?.name}
                                className="h-20 w-20 rounded-full object-cover"
                            />
                        ) : (
                            <div className="flex h-20 w-20 items-center justify-center rounded-full bg-muted">
                                <Users className="h-10 w-10 text-muted-foreground" />
                            </div>
                        )}
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-3xl font-bold tracking-tight text-foreground">
                                    {employee.user?.name}
                                </h1>
                                <Badge
                                    variant={
                                        employee.is_active ? 'success' : 'secondary'
                                    }
                                >
                                    {employee.is_active ? 'Active' : 'Inactive'}
                                </Badge>
                            </div>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {employee.user?.email}
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.visit(`/employees/${employee.id}/edit`)
                            }
                        >
                            <Edit className="mr-2 h-4 w-4" />
                            Edit
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => setShowDeleteModal(true)}
                        >
                            <Trash2 className="mr-2 h-4 w-4" />
                            Delete
                        </Button>
                    </div>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    {/* User Information */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <User className="h-5 w-5 text-muted-foreground" />
                                <CardTitle>User Information</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    Name
                                </p>
                                <p className="mt-1 text-base text-foreground">
                                    {employee.user?.name}
                                </p>
                            </div>
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    Email
                                </p>
                                <div className="mt-1 flex items-center gap-2">
                                    <Mail className="h-4 w-4 text-muted-foreground" />
                                    <a
                                        href={`mailto:${employee.user?.email}`}
                                        className="text-base text-primary hover:underline"
                                    >
                                        {employee.user?.email}
                                    </a>
                                </div>
                            </div>
                            {employee.user?.phone && (
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Phone
                                    </p>
                                    <p className="mt-1 text-base text-foreground">
                                        {employee.user.phone}
                                    </p>
                                </div>
                            )}
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    Role
                                </p>
                                <p className="mt-1 text-base capitalize text-foreground">
                                    {employee.user?.role?.replace('_', ' ')}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Assigned Services */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Briefcase className="h-5 w-5 text-muted-foreground" />
                                <CardTitle>Assigned Services</CardTitle>
                            </div>
                            <CardDescription>
                                Services this employee can provide
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {employee.services && employee.services.length > 0 ? (
                                <div className="space-y-2">
                                    {employee.services.map((service) => (
                                        <div
                                            key={service.id}
                                            className="flex items-center justify-between rounded-md border border-border bg-muted/50 px-3 py-2"
                                        >
                                            <div>
                                                <p className="font-medium text-foreground">
                                                    {service.name}
                                                </p>
                                                {service.category && (
                                                    <p className="text-sm text-muted-foreground">
                                                        {service.category}
                                                    </p>
                                                )}
                                            </div>
                                            <div className="text-right">
                                                <p className="text-sm font-medium text-foreground">
                                                    ${service.price}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {service.duration} min
                                                </p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    No services assigned yet
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    {/* Bio */}
                    {employee.bio && (
                        <Card className="md:col-span-2">
                            <CardHeader>
                                <CardTitle>Bio</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm text-muted-foreground">
                                    {employee.bio}
                                </p>
                            </CardContent>
                        </Card>
                    )}

                    {/* Metadata */}
                    <Card className="md:col-span-2">
                        <CardHeader>
                            <CardTitle>Additional Information</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    Status
                                </p>
                                <p className="mt-1 text-base text-foreground">
                                    <Badge
                                        variant={
                                            employee.is_active
                                                ? 'success'
                                                : 'secondary'
                                        }
                                    >
                                        {employee.is_active ? 'Active' : 'Inactive'}
                                    </Badge>
                                </p>
                            </div>
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    Created
                                </p>
                                <p className="mt-1 text-base text-foreground">
                                    {new Date(
                                        employee.created_at
                                    ).toLocaleDateString()}
                                </p>
                            </div>
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    Last Updated
                                </p>
                                <p className="mt-1 text-base text-foreground">
                                    {new Date(
                                        employee.updated_at
                                    ).toLocaleDateString()}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            {/* Delete Confirmation Modal */}
            <ConfirmationModal
                open={showDeleteModal}
                onOpenChange={setShowDeleteModal}
                title="Delete Employee"
                description={
                    <div className="space-y-2">
                        <p>
                            Are you sure you want to delete{' '}
                            <span className="font-semibold">
                                {employee.user?.name}
                            </span>
                            's employee profile?
                        </p>
                        <p className="text-sm text-muted-foreground">
                            This will remove their employee profile, but their user
                            account will remain.
                        </p>
                    </div>
                }
                confirmLabel="Delete"
                cancelLabel="Cancel"
                onConfirm={handleDelete}
                variant="destructive"
            />
        </AppLayout>
    );
}
