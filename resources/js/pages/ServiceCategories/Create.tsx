import { FormEventHandler } from 'react';
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
import { ArrowLeft, FolderTree } from 'lucide-react';

interface Props {
    parentCategories: ServiceCategory[];
}

export default function CreateServiceCategory({ parentCategories }: Props) {
    const { data, setData, post, processing, errors, isDirty } = useForm({
        name: '',
        description: '',
        parent_id: null as number | null,
        sort_order: 0,
        is_active: true,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/service-categories');
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Categories', href: '/service-categories' },
                { title: 'Create', href: '#' },
            ]}
        >
            <div className="mx-auto max-w-2xl space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight text-foreground">Create Category</h1>
                        <p className="mt-2 text-sm text-muted-foreground">Add a new service category to your business</p>
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
                                    Leave empty to create a root category, or select a parent to create a subcategory
                                </p>
                            </div>
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
                            {processing ? 'Creating...' : 'Create Category'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
