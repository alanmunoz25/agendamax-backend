import { FormEventHandler, useState, useRef } from 'react';
import { router, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import InputError from '@/components/input-error';
import { ArrowLeft, Megaphone, Upload, X } from 'lucide-react';

export default function CreatePromotion() {
    const { data, setData, post, processing, errors, isDirty } = useForm<{
        title: string;
        image: File | null;
        url: string;
        expires_at: string;
        is_active: boolean;
    }>({
        title: '',
        image: null,
        url: '',
        expires_at: '',
        is_active: true,
    });

    const [imagePreview, setImagePreview] = useState<string | null>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const handleImageChange = (file: File | null) => {
        setData('image', file);
        if (file) {
            const reader = new FileReader();
            reader.onload = (e) => setImagePreview(e.target?.result as string);
            reader.readAsDataURL(file);
        } else {
            setImagePreview(null);
        }
    };

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        const file = e.dataTransfer.files[0];
        if (file && file.type.startsWith('image/')) {
            handleImageChange(file);
        }
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/promotions', {
            forceFormData: true,
        });
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Promotions', href: '/promotions' },
                { title: 'Create', href: '#' },
            ]}
        >
            <div className="mx-auto max-w-2xl space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight text-foreground">Create Promotion</h1>
                        <p className="mt-2 text-sm text-muted-foreground">Add a new flyer-style promotion</p>
                    </div>
                    <Button variant="outline" onClick={() => router.visit('/promotions')}>
                        <ArrowLeft className="mr-2 h-4 w-4" />
                        Back
                    </Button>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    {/* Promotion Details */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Megaphone className="h-5 w-5 text-muted-foreground" />
                                <CardTitle>Promotion Details</CardTitle>
                            </div>
                            <CardDescription>Basic information about the promotion</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="title" required>
                                    Title
                                </Label>
                                <Input
                                    id="title"
                                    value={data.title}
                                    onChange={(e) => setData('title', e.target.value)}
                                    placeholder="e.g., Summer Sale, Grand Opening Special"
                                    required
                                />
                                <InputError message={errors.title} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="url">URL</Label>
                                <Input
                                    id="url"
                                    type="url"
                                    value={data.url}
                                    onChange={(e) => setData('url', e.target.value)}
                                    placeholder="https://..."
                                />
                                <InputError message={errors.url} />
                                <p className="text-xs text-muted-foreground">
                                    Optional link for the promotion (e.g., landing page, booking link)
                                </p>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="expires_at">Expiration Date</Label>
                                <Input
                                    id="expires_at"
                                    type="date"
                                    value={data.expires_at}
                                    onChange={(e) => setData('expires_at', e.target.value)}
                                />
                                <InputError message={errors.expires_at} />
                                <p className="text-xs text-muted-foreground">
                                    Leave empty for a promotion with no expiration
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Flyer Image */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Flyer Image</CardTitle>
                            <CardDescription>Upload a JPG or PNG image (max 2MB)</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {imagePreview ? (
                                <div className="relative">
                                    <img
                                        src={imagePreview}
                                        alt="Preview"
                                        className="w-full rounded-lg border object-contain"
                                    />
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        size="sm"
                                        className="absolute right-2 top-2"
                                        onClick={() => handleImageChange(null)}
                                    >
                                        <X className="h-4 w-4" />
                                    </Button>
                                </div>
                            ) : (
                                <div
                                    className="flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-muted-foreground/25 p-12 transition-colors hover:border-muted-foreground/50"
                                    onClick={() => fileInputRef.current?.click()}
                                    onDragOver={(e) => e.preventDefault()}
                                    onDrop={handleDrop}
                                >
                                    <Upload className="mb-4 h-10 w-10 text-muted-foreground/50" />
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Click to upload or drag and drop
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground/75">
                                        JPG, JPEG, or PNG (max 2MB)
                                    </p>
                                </div>
                            )}
                            <input
                                ref={fileInputRef}
                                type="file"
                                accept="image/jpeg,image/jpg,image/png"
                                className="hidden"
                                onChange={(e) => handleImageChange(e.target.files?.[0] || null)}
                            />
                            <InputError message={errors.image} />
                        </CardContent>
                    </Card>

                    {/* Status */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Status</CardTitle>
                            <CardDescription>Control promotion visibility</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center justify-between">
                                <div className="space-y-0.5">
                                    <Label htmlFor="is_active">Published</Label>
                                    <p className="text-sm text-muted-foreground">
                                        Active promotions are visible to clients
                                    </p>
                                </div>
                                <Switch
                                    id="is_active"
                                    checked={data.is_active}
                                    onCheckedChange={(checked) => setData('is_active', checked)}
                                />
                            </div>
                            <InputError message={errors.is_active} />
                        </CardContent>
                    </Card>

                    {/* Actions */}
                    <div className="flex items-center justify-end gap-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => router.visit('/promotions')}
                            disabled={processing}
                        >
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back
                        </Button>
                        <Button type="submit" disabled={processing || !isDirty}>
                            {processing ? 'Creating...' : 'Create Promotion'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
