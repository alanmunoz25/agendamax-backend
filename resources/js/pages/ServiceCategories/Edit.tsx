import { FormEventHandler, useState, useRef } from 'react';
import { router, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
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
import type { ServiceCategory } from '@/types/models';
import { ArrowLeft, FolderTree, Upload, X } from 'lucide-react';

interface Props {
    category: ServiceCategory;
    parentCategories: ServiceCategory[];
}

export default function EditServiceCategory({ category, parentCategories }: Props) {
    const { data, setData, post, processing, errors, isDirty } = useForm<{
        _method: string;
        name: string;
        description: string;
        image: File | null;
        parent_id: number | null;
        sort_order: number;
        is_active: boolean;
    }>({
        _method: 'put',
        name: category.name || '',
        description: category.description || '',
        image: null,
        parent_id: category.parent_id || null,
        sort_order: category.sort_order ?? 0,
        is_active: category.is_active ?? true,
    });

    const [imagePreview, setImagePreview] = useState<string | null>(
        category.image_url || null,
    );
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
        post(`/service-categories/${category.id}`, {
            forceFormData: true,
        });
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Categories', href: '/service-categories' },
                { title: category.name, href: '#' },
            ]}
        >
            <div className="mx-auto max-w-2xl space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight text-foreground">Edit Category</h1>
                        <p className="mt-2 text-sm text-muted-foreground">Update {category.name} details</p>
                    </div>
                    <Button variant="outline" onClick={() => router.visit('/service-categories')}>
                        <ArrowLeft className="mr-2 h-4 w-4" />
                        Back
                    </Button>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    {/* Category Information */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <FolderTree className="h-5 w-5 text-muted-foreground" />
                                <CardTitle>Category Information</CardTitle>
                            </div>
                            <CardDescription>Basic details about the category</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="name" required>
                                    Category Name
                                </Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="e.g., Hair Services, Nail Care, Spa Treatments"
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="description">Description</Label>
                                <Textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder="Describe this category..."
                                    rows={3}
                                />
                                <InputError message={errors.description} />
                            </div>

                            <div className="space-y-2">
                                <Label>Parent Category</Label>
                                <Select
                                    value={data.parent_id ? String(data.parent_id) : 'none'}
                                    onValueChange={(value) =>
                                        setData('parent_id', value === 'none' ? null : Number(value))
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select parent category" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">None (Root Category)</SelectItem>
                                        {parentCategories.map((cat) => (
                                            <SelectItem key={cat.id} value={String(cat.id)}>
                                                {cat.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.parent_id} />
                                <p className="text-xs text-muted-foreground">
                                    Leave empty to keep as a root category, or select a parent to make it a subcategory
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Category Image */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Category Image</CardTitle>
                            <CardDescription>Upload a JPG, PNG, or WebP image (max 2MB)</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {imagePreview ? (
                                <div className="relative">
                                    <img
                                        src={imagePreview}
                                        alt="Preview"
                                        className="h-48 w-full rounded-lg border object-contain"
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
                                        JPG, JPEG, PNG, or WebP (max 2MB)
                                    </p>
                                </div>
                            )}
                            <input
                                ref={fileInputRef}
                                type="file"
                                accept="image/jpeg,image/jpg,image/png,image/webp"
                                className="hidden"
                                onChange={(e) => handleImageChange(e.target.files?.[0] || null)}
                            />
                            <InputError message={errors.image} />
                        </CardContent>
                    </Card>

                    {/* Display Settings */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Display Settings</CardTitle>
                            <CardDescription>Control how this category appears</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="sort_order">Sort Order</Label>
                                <Input
                                    id="sort_order"
                                    type="number"
                                    min="0"
                                    value={data.sort_order}
                                    onChange={(e) => setData('sort_order', parseInt(e.target.value) || 0)}
                                    placeholder="0"
                                />
                                <InputError message={errors.sort_order} />
                                <p className="text-xs text-muted-foreground">
                                    Categories with lower numbers appear first
                                </p>
                            </div>

                            <div className="flex items-center justify-between">
                                <div className="space-y-0.5">
                                    <Label htmlFor="is_active">Active Status</Label>
                                    <p className="text-sm text-muted-foreground">
                                        Only active categories are visible to clients
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
                            onClick={() => router.visit('/service-categories')}
                            disabled={processing}
                        >
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back
                        </Button>
                        <Button type="submit" disabled={processing || !isDirty}>
                            {processing ? 'Saving...' : 'Save Changes'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
