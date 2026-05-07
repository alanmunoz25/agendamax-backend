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
import { type SharedData } from '@/types';
import { Save, X } from 'lucide-react';
import { Head, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

interface Props {
    targetUser: User;
    businesses: Pick<Business, 'id' | 'name'>[];
    availableRoles: string[];
}

export default function EditUser({ targetUser, businesses, availableRoles }: Props) {
    const { t } = useTranslation();
    const { permissions } = usePage<SharedData>().props;

    const breadcrumbs = [
        { title: t('breadcrumbs.dashboard'), href: '/dashboard' },
        { title: t('breadcrumbs.users'), href: '/users' },
        { title: targetUser.name, href: `/users/${targetUser.id}/edit` },
    ];

    const roleLabels: Record<string, string> = {
        super_admin: t('users.role_super_admin'),
        business_admin: t('users.role_business_admin'),
        employee: t('users.role_employee'),
        client: t('users.role_client'),
    };

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
            <Head title={`${t('users.edit_title')} - ${targetUser.name}`} />
            <div className="mx-auto max-w-2xl space-y-6 p-4">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight text-foreground">{t('users.edit_title')}</h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        {t('users.edit_subtitle', { name: targetUser.name })}
                    </p>
                </div>

                {/* User Info (read-only) */}
                <Card>
                    <CardHeader>
                        <CardTitle>{t('users.info_card_title')}</CardTitle>
                        <CardDescription>{t('users.info_card_readonly_desc')}</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <p className="text-xs font-medium text-muted-foreground">{t('common.name')}</p>
                                <p className="text-sm">{targetUser.name}</p>
                            </div>
                            <div>
                                <p className="text-xs font-medium text-muted-foreground">{t('common.email')}</p>
                                <p className="text-sm">{targetUser.email}</p>
                            </div>
                            <div>
                                <p className="text-xs font-medium text-muted-foreground">{t('users.phone_label')}</p>
                                <p className="text-sm">{targetUser.phone || t('users.not_set')}</p>
                            </div>
                            <div>
                                <p className="text-xs font-medium text-muted-foreground">{t('users.current_business_label')}</p>
                                <p className="text-sm">{targetUser.business?.name || t('common.none')}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <form onSubmit={submit} className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('users.role_card_title')}</CardTitle>
                            <CardDescription>{t('users.role_card_edit_desc')}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label>{t('users.role_label')}</Label>
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
                            <X className="mr-2 h-4 w-4" />
                            {t('common.cancel')}
                        </Button>
                        <Button type="submit" disabled={processing || !isDirty}>
                            <Save className="mr-2 h-4 w-4" />
                            {processing ? t('common.saving') : t('common.save_changes')}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
