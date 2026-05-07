import { FormEventHandler, useState } from 'react';
import { useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
import type { Business } from '@/types/models';
import { type SharedData } from '@/types';
import { UserPlus, Eye, EyeOff } from 'lucide-react';
import { Head, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

interface Props {
    businesses: Pick<Business, 'id' | 'name'>[];
    availableRoles: string[];
}

export default function CreateUser({ businesses, availableRoles }: Props) {
    const { t } = useTranslation();
    const { permissions } = usePage<SharedData>().props;
    const [showPassword, setShowPassword] = useState(false);

    const breadcrumbs = [
        { title: t('breadcrumbs.dashboard'), href: '/dashboard' },
        { title: t('breadcrumbs.users'), href: '/users' },
        { title: t('breadcrumbs.create'), href: '/users/create' },
    ];

    const roleLabels: Record<string, string> = {
        super_admin: t('users.role_super_admin'),
        business_admin: t('users.role_business_admin'),
        employee: t('users.role_employee'),
        client: t('users.role_client'),
    };

    const { data, setData, post, processing, errors, isDirty } = useForm({
        name: '',
        email: '',
        password: '',
        role: availableRoles[availableRoles.length - 1] || 'client',
        business_id: '',
        phone: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/users', {
            data: {
                ...data,
                business_id: data.business_id || null,
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('users.create_title')} />
            <div className="mx-auto max-w-2xl space-y-6 p-4">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight text-foreground">{t('users.create_title')}</h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        {t('users.create_subtitle')}
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <UserPlus className="h-5 w-5 text-muted-foreground" />
                                <CardTitle>{t('users.info_card_title')}</CardTitle>
                            </div>
                            <CardDescription>{t('users.info_card_desc')}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">{t('users.name_label')}</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="ej: Juan García"
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="email">{t('common.email')}</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        placeholder="usuario@ejemplo.com"
                                        required
                                    />
                                    <InputError message={errors.email} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="phone">{t('users.phone_label')}</Label>
                                    <Input
                                        id="phone"
                                        value={data.phone}
                                        onChange={(e) => setData('phone', e.target.value)}
                                        placeholder="+1 (809) 555-1234"
                                    />
                                    <InputError message={errors.phone} />
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="password">{t('users.password_label')}</Label>
                                <div className="relative">
                                    <Input
                                        id="password"
                                        type={showPassword ? 'text' : 'password'}
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                        placeholder={t('users.password_placeholder')}
                                        required
                                        className="pr-10"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowPassword(!showPassword)}
                                        className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                    >
                                        {showPassword ? (
                                            <EyeOff className="h-4 w-4" />
                                        ) : (
                                            <Eye className="h-4 w-4" />
                                        )}
                                    </button>
                                </div>
                                <InputError message={errors.password} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{t('users.role_card_title')}</CardTitle>
                            <CardDescription>{t('users.role_card_desc')}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label>{t('users.role_label')}</Label>
                                <Select value={data.role} onValueChange={(v) => setData('role', v)}>
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
                                    <Label>{t('users.business_label')}</Label>
                                    <Select
                                        value={data.business_id || 'none'}
                                        onValueChange={(v) => setData('business_id', v === 'none' ? '' : v)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder={t('users.no_business')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">{t('users.no_business')}</SelectItem>
                                            {businesses.map((b) => (
                                                <SelectItem key={b.id} value={String(b.id)}>
                                                    {b.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.business_id} />
                                    <p className="text-xs text-muted-foreground">
                                        {t('users.business_hint')}
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
                            {t('common.cancel')}
                        </Button>
                        <Button type="submit" disabled={processing || !isDirty}>
                            <UserPlus className="mr-2 h-4 w-4" />
                            {processing ? t('common.creating') : t('users.create_title')}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
