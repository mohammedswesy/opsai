import { Button } from '@/components/Button';
import { Link, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';
import GuestLayout from '@/layouts/GuestLayout';
import { controlClassName, FormField } from '@/components/FormField';
import { useI18n } from '@/i18n';

export default function Login() {
    const { t } = useI18n();
    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });

    return (
        <form
            className="space-y-4"
            onSubmit={(event) => {
                event.preventDefault();
                form.post('/login');
            }}
        >
            <h1 className="text-xl font-semibold">{t.guest.login}</h1>
            <FormField label={t.guest.email} error={form.errors.email}>
                <input className={controlClassName} type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} autoComplete="username" />
            </FormField>
            <FormField label={t.guest.password} error={form.errors.password}>
                <input className={controlClassName} type="password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} autoComplete="current-password" />
            </FormField>
            <label className="flex items-center gap-2 text-sm text-zinc-600">
                <input type="checkbox" checked={form.data.remember} onChange={(event) => form.setData('remember', event.target.checked)} />
                {t.guest.remember}
            </label>
            <Button variant="primary" className="w-full" type="submit" disabled={form.processing}>
                {t.guest.login}
            </Button>
            <div className="flex justify-between text-sm">
                <Link href="/forgot-password" className="text-teal-800">
                    {t.guest.forgot}
                </Link>
                <Link href="/register" className="text-teal-800">
                    {t.guest.noAccount}
                </Link>
            </div>
        </form>
    );
}

Login.layout = (page: ReactNode) => <GuestLayout>{page}</GuestLayout>;
