import { Link, useForm } from '@inertiajs/react';
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
            <h1 className="text-xl font-semibold">{t.guest.forgot}</h1>
            <FormField label={t.guest.email} error={form.errors.email}>
                <input className={controlClassName} type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} />
            </FormField>
            <button type="submit" disabled={form.processing} className="w-full rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white">
                {t.guest.sendReset}
            </button>
            <Link href="/login" className="block text-sm text-teal-800">
                {t.guest.backToLogin}
            </Link>
        </form>
    );
}

ForgotPassword.layout = (page: ReactNode) => <GuestLayout>{page}</GuestLayout>;
