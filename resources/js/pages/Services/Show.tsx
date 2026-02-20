import { useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { ConfirmationModal } from '@/components/confirmation-modal';
import type { Service } from '@/types/models';
import {
    Briefcase,
    Clock,
    DollarSign,
    Tag,
    Pencil,
    Trash2,
    FileText,
} from 'lucide-react';

interface Props {
    service: Service;
}

export default function ShowService({ service }: Props) {
    const [showDeleteModal, setShowDeleteModal] = useState(false);

    const handleDelete = () => {
        router.delete(`/services/${service.id}`, {
            onSuccess: () => router.visit('/services'),
        });
    };

    return (
        <AppLayout
            title={service.name}
            breadcrumbs={[
                { label: 'Dashboard', href: '/dashboard' },
                { label: 'Services', href: '/services' },
                { label: service.name },
            ]}
        >
            <div className="mx-auto max-w-3xl space-y-6">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight text-foreground">
                            {service.name}
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Service details and information
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            onClick={() => router.visit(`/services/${service.id}/edit`)}
                        >
                            <Pencil className="mr-2 h-4 w-4" />
                            Edit
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => setShowDeleteModal(true)}
                        >
                            <Trash2 className="mr-2 h-4 w-4" />
                            Delete
                        </Button>
                    </div>
                </div>

                {/* Status Badge */}
                <div>
                    <span
                        className={`inline-flex items-center rounded-full px-3 py-1 text-sm font-medium ${
                            service.is_active
                                ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-400'
                        }`}
                    >
                        {service.is_active ? 'Active' : 'Inactive'}
                    </span>
                </div>

                {/* Service Information */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <Briefcase className="h-5 w-5 text-muted-foreground" />
                            <CardTitle>Service Information</CardTitle>
                        </div>
                        <CardDescription>
                            Basic details about this service
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-1">
                                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Tag className="h-4 w-4" />
                                    <span>Category</span>
                                </div>
                                <p className="font-medium text-foreground">
                                    {service.service_category?.parent
                                        ? `${service.service_category.parent.name} / ${service.service_category.name}`
                                        : service.service_category?.name || service.category || 'Uncategorized'}
                                </p>
                            </div>

                            <div className="space-y-1">
                                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <DollarSign className="h-4 w-4" />
                                    <span>Price</span>
                                </div>
                                <p className="font-medium text-foreground">
                                    ${Number(service.price).toFixed(2)}
                                </p>
                            </div>

                            <div className="space-y-1">
                                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Clock className="h-4 w-4" />
                                    <span>Duration</span>
                                </div>
                                <p className="font-medium text-foreground">
                                    {service.duration} minutes
                                </p>
                            </div>
                        </div>

                        {service.description && (
                            <div className="space-y-2 pt-4">
                                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <FileText className="h-4 w-4" />
                                    <span>Description</span>
                                </div>
                                <p className="text-sm text-foreground leading-relaxed">
                                    {service.description}
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Metadata */}
                <Card>
                    <CardHeader>
                        <CardTitle>Metadata</CardTitle>
                        <CardDescription>
                            System information about this service
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-muted-foreground">Created</span>
                            <span className="font-medium text-foreground">
                                {new Date(service.created_at).toLocaleDateString('en-US', {
                                    year: 'numeric',
                                    month: 'long',
                                    day: 'numeric',
                                })}
                            </span>
                        </div>
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-muted-foreground">Last Updated</span>
                            <span className="font-medium text-foreground">
                                {new Date(service.updated_at).toLocaleDateString('en-US', {
                                    year: 'numeric',
                                    month: 'long',
                                    day: 'numeric',
                                })}
                            </span>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Delete Confirmation Modal */}
            <ConfirmationModal
                open={showDeleteModal}
                onClose={() => setShowDeleteModal(false)}
                onConfirm={handleDelete}
                title="Delete Service"
                description={`Are you sure you want to delete "${service.name}"? This action cannot be undone.`}
                variant="destructive"
            />
        </AppLayout>
    );
}
