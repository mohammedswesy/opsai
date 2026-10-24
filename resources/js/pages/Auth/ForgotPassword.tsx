import { Button } from '@/components/Button';
import { Head, Link, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { controlClassName, FormField } from '@/components/FormField';
import { useI18n } from '@/i18n';
import GuestLayout from '@/layouts/GuestLayout';

export default function ForgotPassword() {
    const { t } = useI18n();
    const form = useForm({ email: '' });

    return (
        <form
            className="space-y-4"
            onSubmit={(event) => {
                event.preventDefault();
                form.post('/forgot-password');
            }}
        >
            <Head title={t.guest.forgot} />
            <h1 className="text-xl font-semibold text-ink">{t.guest.forgot}</h1>
            <FormField label={t.guest.email} error={form.errors.email}>
                <input className={controlClassName} type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} />
            </FormField>
            <Button variant="primary" className="w-full" type="submit" disabled={form.processing}>
                {t.guest.sendReset}
            </Button>
            <Link href="/login" className="block text-body font-medium text-brand hover:text-brand-strong">
                {t.guest.backToLogin}
            </Link>
        </form>
    );
}

ForgotPassword.layout = (page: ReactNode) => <GuestLayout>{page}</GuestLayout>;
