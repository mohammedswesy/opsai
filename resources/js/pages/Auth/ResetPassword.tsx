import { Button } from '@/components/Button';
import { Link, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { controlClassName, FormField } from '@/components/FormField';
import { useI18n } from '@/i18n';
import GuestLayout from '@/layouts/GuestLayout';

export default function ResetPassword({ token, email }: { token: string; email: string }) {
    const { t } = useI18n();
    const form = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    return (
        <form
            className="space-y-4"
            onSubmit={(event) => {
                event.preventDefault();
                form.post('/reset-password');
            }}
        >
            <h1 className="text-xl font-semibold">{t.guest.resetPassword}</h1>
            <FormField label={t.guest.email} error={form.errors.email}>
                <input className={controlClassName} type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} />
            </FormField>
            <FormField label={t.guest.password} error={form.errors.password}>
                <input className={controlClassName} type="password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} />
            </FormField>
            <FormField label={t.guest.passwordConfirmation}>
                <input className={controlClassName} type="password" value={form.data.password_confirmation} onChange={(event) => form.setData('password_confirmation', event.target.value)} />
            </FormField>
            <Button variant="primary" className="w-full" type="submit" disabled={form.processing}>
                {t.guest.resetPassword}
            </Button>
            <Link href="/login" className="block text-sm text-teal-800">
                {t.guest.backToLogin}
            </Link>
        </form>
    );
}

ResetPassword.layout = (page: ReactNode) => <GuestLayout>{page}</GuestLayout>;
