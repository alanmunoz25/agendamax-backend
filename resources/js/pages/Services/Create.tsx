import { FormEventHandler, useMemo, useState } from 'react';
import { useForm } from '@inertiajs/react';
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
import { Briefcase } from 'lucide-react';

interface Props {
    serviceCategories: ServiceCategory[];
}

export default function CreateService({ serviceCategories }: Props) {
    const [selectedParentId, setSelectedParentId] = useState<string>('');

    const { data, setData, post, processing, errors, isDirty } = useForm({
        name: '',
        description: '',
        duration: 60,
        price: 0,
        category: '',
        service_category_id: null as number | null,
        is_active: true,
    });

    const subcategories = useMemo(() => {
        if (!selectedParentId) return [];
        const parent = serviceCategories.find((c) => c.id === Number(selectedParentId));
        return parent?.children || [];
    }, [selectedParentId, serviceCategories]);

    const handleParentChange = (value: string) => {
        setSelectedParentId(value === 'none' ? '' : value);
        setData((prev) => ({
            ...prev,
            service_category_id: null,
            category: value === 'none' ? '' : serviceCategories.find((c) => c.id === Number(value))?.name || '',
        }));
    };

    const handleSubcategoryChange = (value: string) => {
        setData('service_category_id', value === 'none' ? null : Number(value));
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/services');
    };

    return (
        <AppLayout
            title="Create Service"
            breadcrumbs={[
                { label: 'Dashboard', href: '/dashboard' },
                { label: 'Services', href: '/services' },
                { label: 'Create' },
            ]}
        >
            <div className="mx-auto max-w-2xl space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-3xl font-bold tracking-tight text-foreground">
                        Create Service
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Add a new service to your business offerings
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    {/* Basic Information */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Briefcase className="h-5 w-5 text-muted-foreground" />
                                <CardTitle>Service Information</CardTitle>
                            </div>
                            <CardDescription>
                                Basic details about the service
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="name" required>
                                    Service Name
                                </Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="e.g., Haircut, Massage, Consultation"
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="description">Description</Label>
                                <Textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) =>
                                        setData('description', e.target.value)
                                    }
                                    placeholder="Describe the service in detail..."
                                    rows={3}
                                />
                                <InputError message={errors.description} />
                                <p className="text-xs text-muted-foreground">
                                    Provide a clear description of what this service
                                    includes
                                </p>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>Category</Label>
                                    <Select
                                        value={selectedParentId || 'none'}
                                        onValueChange={handleParentChange}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select category" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">No category</SelectItem>
                                            {serviceCategories.map((cat) => (
                                                <SelectItem key={cat.id} value={String(cat.id)}>
                                                    {cat.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.category} />
                                </div>

                                <div className="space-y-2">
                                    <Label>Subcategory</Label>
                                    <Select
                                        value={data.service_category_id ? String(data.service_category_id) : 'none'}
                                        onValueChange={handleSubcategoryChange}
                                        disabled={!selectedParentId || subcategories.length === 0}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select subcategory" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">No subcategory</SelectItem>
                                            {subcategories.map((sub) => (
                                                <SelectItem key={sub.id} value={String(sub.id)}>
                                                    {sub.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.service_category_id} />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Pricing & Duration */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Pricing & Duration</CardTitle>
                            <CardDescription>
                                Set the price and estimated duration
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="price" required>
                                        Price ($)
                                    </Label>
                                    <Input
                                        id="price"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="999999.99"
                                        value={data.price}
                                        onChange={(e) =>
                                            setData('price', parseFloat(e.target.value))
                                        }
                                        placeholder="0.00"
                                        required
                                    />
                                    <InputError message={errors.price} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="duration" required>
                                        Duration (minutes)
                                    </Label>
                                    <Input
                                        id="duration"
                                        type="number"
                                        min="15"
                                        max="480"
                                        step="15"
                                        value={data.duration}
                                        onChange={(e) =>
                                            setData('duration', parseInt(e.target.value))
                                        }
                                        placeholder="60"
                                        required
                                    />
                                    <InputError message={errors.duration} />
                                    <p className="text-xs text-muted-foreground">
                                        Estimated time to complete the service
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Availability */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Availability</CardTitle>
                            <CardDescription>
                                Control whether this service is bookable
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center justify-between">
                                <div className="space-y-0.5">
                                    <Label htmlFor="is_active">Active Status</Label>
                                    <p className="text-sm text-muted-foreground">
                                        Only active services can be booked by clients
                                    </p>
                                </div>
                                <Switch
                                    id="is_active"
                                    checked={data.is_active}
                                    onCheckedChange={(checked) =>
                                        setData('is_active', checked)
                                    }
                                />
                            </div>
                            <InputError message={errors.is_active} className="mt-2" />
                        </CardContent>
                    </Card>

                    {/* Actions */}
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
                            {processing ? 'Creating...' : 'Create Service'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
