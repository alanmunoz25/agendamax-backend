// Components
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { logout } from '@/routes';
import { send } from '@/routes/verification';
import { Form, Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

export default function VerifyEmail({ status }: { status?: string }) {
    const { t } = useTranslation();

    return (
        <AuthLayout
            title={t('auth.verify_title')}
            description={t('auth.verify_description')}
        >
            <Head title={t('auth.verify_title')} />

            {status === 'verification-link-sent' && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {t('auth.resend_verification')}
                </div>
            )}

            <Form {...send.form()} className="space-y-6 text-center">
                {({ processing }) => (
                    <>
                        <Button disabled={processing} variant="secondary">
                            {processing && <Spinner />}
                            {t('auth.resend_verification')}
                        </Button>

                        <TextLink
                            href={logout()}
                            className="mx-auto block text-sm"
                        >
                            {t('user_menu.logout')}
                        </TextLink>
                    </>
                )}
            </Form>
        </AuthLayout>
    );
}
