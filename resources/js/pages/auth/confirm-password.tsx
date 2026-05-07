import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { store } from '@/routes/password/confirm';
import { Form, Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

export default function ConfirmPassword() {
    const { t } = useTranslation();

    return (
        <AuthLayout
            title={t('auth_confirm_password.title')}
            description={t('auth_confirm_password.description')}
        >
            <Head title={t('auth_confirm_password.title')} />

            <Form {...store.form()} resetOnSuccess={['password']}>
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="password">{t('auth_confirm_password.password_label')}</Label>
                            <Input
                                id="password"
                                type="password"
                                name="password"
                                placeholder={t('auth_confirm_password.password_label')}
                                autoComplete="current-password"
                                autoFocus
                            />

                            <InputError message={errors.password} />
                        </div>

                        <div className="flex items-center">
                            <Button
                                className="w-full"
                                disabled={processing}
                                data-test="confirm-password-button"
                            >
                                {processing && <Spinner />}
                                {t('auth_confirm_password.confirm_btn')}
                            </Button>
                        </div>
                    </div>
                )}
            </Form>
        </AuthLayout>
    );
}
