import { FormEventHandler } from 'react';
import { useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import InputError from '@/components/input-error';
import { UserPlus, Save, X } from 'lucide-react';
import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

export default function CreateClient() {
    const { t } = useTranslation();

    const { data, setData, post, processing, errors, isDirty } = useForm({
        name: '',
        email: '',
        phone: '',
        avatar_url: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/clients');
    };

    return (
        <AppLayout
            title={t('clients.create_title')}
            breadcrumbs={[
                { label: t('breadcrumbs.dashboard'), href: '/dashboard' },
                { label: t('breadcrumbs.clients'), href: '/clients' },
                { label: t('breadcrumbs.add_new') },
            ]}
        >
            <div className="mx-auto max-w-2xl space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-3xl font-bold tracking-tight text-foreground flex items-center gap-2">
                        <UserPlus className="h-8 w-8" />
                        {t('clients.create_title')}
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        {t('clients.create_subtitle')}
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('clients.info_card_title')}</CardTitle>
                            <CardDescription>
                                {t('clients.info_card_desc')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {/* Name */}
                            <div>
                                <Label htmlFor="name">{t('clients.name_label')} *</Label>
                                <Input
                                    id="name"
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="Juan Pérez"
                                    className="mt-1"
                                    autoFocus
                                />
                                <InputError message={errors.name} className="mt-2" />
                            </div>

                            {/* Email */}
                            <div>
                                <Label htmlFor="email">{t('clients.email_label')} *</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    placeholder="juan@ejemplo.com"
                                    className="mt-1"
                                />
                                <InputError message={errors.email} className="mt-2" />
                            </div>

                            {/* Phone */}
                            <div>
                                <Label htmlFor="phone">{t('clients.phone_label')}</Label>
                                <Input
                                    id="phone"
                                    type="tel"
                                    value={data.phone}
                                    onChange={(e) => setData('phone', e.target.value)}
                                    placeholder="+1 (809) 555-1234"
                                    className="mt-1"
                                />
                                <InputError message={errors.phone} className="mt-2" />
                            </div>

                            {/* Avatar URL */}
                            <div>
                                <Label htmlFor="avatar_url">{t('clients.avatar_label')}</Label>
                                <Input
                                    id="avatar_url"
                                    type="url"
                                    value={data.avatar_url}
                                    onChange={(e) => setData('avatar_url', e.target.value)}
                                    placeholder="https://example.com/avatar.jpg"
                                    className="mt-1"
                                />
                                <InputError message={errors.avatar_url} className="mt-2" />
                            </div>
                        </CardContent>
                    </Card>

                    {/* Actions */}
                    <div className="flex items-center justify-between gap-4">
                        <Link
                            href="/clients"
                            className="inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:bg-accent hover:text-accent-foreground h-10 px-4 py-2"
                        >
                            <X className="mr-2 h-4 w-4" />
                            {t('common.cancel')}
                        </Link>
                        <Button type="submit" disabled={processing || !isDirty}>
                            <Save className="mr-2 h-4 w-4" />
                            {processing ? t('common.creating') : t('clients.create_btn')}
                        </Button>
                    </div>
                </form>

                {/* Help Card */}
                <Card className="bg-muted/50">
                    <CardHeader>
                        <CardTitle className="text-base">{t('clients.note_title')}</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm text-muted-foreground">
                        <p>{t('clients.note_1')}</p>
                        <p>{t('clients.note_2')}</p>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
