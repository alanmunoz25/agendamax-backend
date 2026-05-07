import { FormEventHandler } from 'react';
import { useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import InputError from '@/components/input-error';
import type { Service, User } from '@/types/models';
import { UserPlus, Briefcase } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface Props {
    availableUsers: Pick<User, 'id' | 'name' | 'email' | 'role'>[];
    services: Pick<Service, 'id' | 'name' | 'category'>[];
}

export default function CreateEmployee({ availableUsers, services }: Props) {
    const { t } = useTranslation();

    const { data, setData, post, processing, errors, isDirty } = useForm({
        user_id: '',
        photo_url: '',
        bio: '',
        is_active: true,
        service_ids: [] as number[],
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/employees');
    };

    const toggleService = (serviceId: number) => {
        setData(
            'service_ids',
            data.service_ids.includes(serviceId)
                ? data.service_ids.filter((id) => id !== serviceId)
                : [...data.service_ids, serviceId]
        );
    };

    // Group services by category
    const servicesByCategory = services.reduce(
        (acc, service) => {
            const category = service.category || t('common.uncategorized');
            if (!acc[category]) {
                acc[category] = [];
            }
            acc[category].push(service);
            return acc;
        },
        {} as Record<string, typeof services>
    );

    return (
        <AppLayout
            title={t('employees.create_title')}
            breadcrumbs={[
                { label: t('breadcrumbs.dashboard'), href: '/dashboard' },
                { label: t('breadcrumbs.employees'), href: '/employees' },
                { label: t('breadcrumbs.create') },
            ]}
        >
            <div className="mx-auto max-w-2xl space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-3xl font-bold tracking-tight text-foreground">
                        {t('employees.create_title')}
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        {t('employees.create_subtitle')}
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    {/* Employee Information */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <UserPlus className="h-5 w-5 text-muted-foreground" />
                                <CardTitle>{t('employees.info_card_title')}</CardTitle>
                            </div>
                            <CardDescription>
                                {t('employees.info_card_desc')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="user_id" required>
                                    {t('employees.user_account_label')}
                                </Label>
                                <Select
                                    value={data.user_id}
                                    onValueChange={(value) =>
                                        setData('user_id', value)
                                    }
                                >
                                    <SelectTrigger id="user_id">
                                        <SelectValue placeholder={t('employees.user_placeholder')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {availableUsers.map((user) => (
                                            <SelectItem
                                                key={user.id}
                                                value={user.id.toString()}
                                            >
                                                <div className="flex flex-col">
                                                    <span>{user.name}</span>
                                                    <span className="text-xs text-muted-foreground">
                                                        {user.email}
                                                    </span>
                                                </div>
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.user_id} />
                                <p className="text-xs text-muted-foreground">
                                    {t('employees.user_hint')}
                                </p>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="photo_url">{t('employees.photo_label')}</Label>
                                <Input
                                    id="photo_url"
                                    type="url"
                                    value={data.photo_url}
                                    onChange={(e) =>
                                        setData('photo_url', e.target.value)
                                    }
                                    placeholder="https://example.com/photo.jpg"
                                />
                                <InputError message={errors.photo_url} />
                                <p className="text-xs text-muted-foreground">
                                    {t('employees.photo_hint')}
                                </p>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="bio">{t('employees.bio_label')}</Label>
                                <Textarea
                                    id="bio"
                                    value={data.bio}
                                    onChange={(e) => setData('bio', e.target.value)}
                                    placeholder={t('employees.bio_placeholder')}
                                    rows={3}
                                />
                                <InputError message={errors.bio} />
                                <p className="text-xs text-muted-foreground">
                                    {t('employees.bio_hint')}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Service Assignment */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Briefcase className="h-5 w-5 text-muted-foreground" />
                                <CardTitle>{t('employees.service_assignment_title')}</CardTitle>
                            </div>
                            <CardDescription>
                                {t('employees.service_assignment_desc')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {Object.keys(servicesByCategory).length > 0 ? (
                                Object.entries(servicesByCategory).map(
                                    ([category, categoryServices]) => (
                                        <div key={category} className="space-y-2">
                                            <h3 className="text-sm font-medium text-foreground">
                                                {category}
                                            </h3>
                                            <div className="space-y-2">
                                                {categoryServices.map((service) => (
                                                    <div
                                                        key={service.id}
                                                        className="flex items-center space-x-2"
                                                    >
                                                        <Checkbox
                                                            id={`service-${service.id}`}
                                                            checked={data.service_ids.includes(
                                                                service.id
                                                            )}
                                                            onCheckedChange={() =>
                                                                toggleService(
                                                                    service.id
                                                                )
                                                            }
                                                        />
                                                        <Label
                                                            htmlFor={`service-${service.id}`}
                                                            className="cursor-pointer text-sm font-normal"
                                                        >
                                                            {service.name}
                                                        </Label>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )
                                )
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    {t('employees.no_services_available')}
                                </p>
                            )}
                            <InputError message={errors.service_ids} />
                        </CardContent>
                    </Card>

                    {/* Availability */}
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('employees.availability_card_title')}</CardTitle>
                            <CardDescription>
                                {t('employees.availability_card_desc')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center justify-between">
                                <div className="space-y-0.5">
                                    <Label htmlFor="is_active">{t('employees.active_status_label')}</Label>
                                    <p className="text-sm text-muted-foreground">
                                        {t('employees.active_status_hint')}
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
                            {t('common.cancel')}
                        </Button>
                        <Button type="submit" disabled={processing || !isDirty}>
                            {processing ? t('common.creating') : t('employees.create_title')}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
