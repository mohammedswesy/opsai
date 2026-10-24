import { Button } from '@/components/Button';
import { Link, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { controlClassName, FormField } from '@/components/FormField';
import { useI18n } from '@/i18n';
import GuestLayout from '@/layouts/GuestLayout';
import { optionLabel } from '@/lib/labels';

export default function Register({
    countries,
    currencies,
    locales,
}: {
    countries: Record<string, string>;
    currencies: string[];
    locales: string[];
}) {
    const { t } = useI18n();
    const form = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        organization_name: '',
        country: 'SA',
        timezone: 'Asia/Riyadh',
        currency: 'SAR',
        locale: 'ar',
    });

    return (
        <form
            className="space-y-4"
            onSubmit={(event) => {
                event.preventDefault();
                form.post('/register');
            }}
        >
            <h1 className="text-xl font-semibold">{t.guest.register}</h1>
            <FormField label={t.guest.name} error={form.errors.name}>
                <input className={controlClassName} value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />
            </FormField>
            <FormField label={t.guest.email} error={form.errors.email}>
                <input className={controlClassName} type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} />
            </FormField>
            <FormField label={t.guest.password} error={form.errors.password}>
                <input className={controlClassName} type="password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} />
            </FormField>
            <FormField label={t.guest.passwordConfirmation} error={form.errors.password_confirmation}>
                <input className={controlClassName} type="password" value={form.data.password_confirmation} onChange={(event) => form.setData('password_confirmation', event.target.value)} />
            </FormField>
            <FormField label={t.guest.organizationName} error={form.errors.organization_name}>
                <input className={controlClassName} value={form.data.organization_name} onChange={(event) => form.setData('organization_name', event.target.value)} />
            </FormField>
            <FormField label={t.guest.country} error={form.errors.country}>
                <select className={controlClassName} value={form.data.country} onChange={(event) => form.setData('country', event.target.value)}>
                    {Object.entries(countries).map(([code, label]) => (
                        <option key={code} value={code}>
                            {label}
                        </option>
                    ))}
                </select>
            </FormField>
            <FormField label={t.guest.timezone} error={form.errors.timezone}>
                <input className={controlClassName} value={form.data.timezone} onChange={(event) => form.setData('timezone', event.target.value)} />
            </FormField>
            <FormField label={t.guest.currency} error={form.errors.currency}>
                <select className={controlClassName} value={form.data.currency} onChange={(event) => form.setData('currency', event.target.value)}>
                    {currencies.map((currency) => (
                        <option key={currency} value={currency}>
                            {currency}
                        </option>
                    ))}
                </select>
            </FormField>
            <FormField label={t.guest.locale} error={form.errors.locale}>
                <select className={controlClassName} value={form.data.locale} onChange={(event) => form.setData('locale', event.target.value)}>
                    {locales.map((locale) => (
                        <option key={locale} value={locale}>
                            {optionLabel(t.labels, locale)}
                        </option>
                    ))}
                </select>
            </FormField>
            <Button variant="primary" className="w-full" type="submit" disabled={form.processing}>
                {t.guest.register}
            </Button>
            <Link href="/login" className="block text-sm text-teal-800">
                {t.guest.hasAccount}
            </Link>
        </form>
    );
}

Register.layout = (page: ReactNode) => <GuestLayout>{page}</GuestLayout>;
