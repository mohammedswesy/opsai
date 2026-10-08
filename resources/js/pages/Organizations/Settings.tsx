import { useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { controlClassName, FormField } from '@/components/FormField';
import { PageHeader } from '@/components/PageHeader';
import { useI18n } from '@/i18n';
import AppLayout from '@/layouts/AppLayout';

type OrganizationForm = {
    id: string;
    name: string;
    slug: string;
    legal_name: string | null;
    tax_number: string | null;
    phone: string | null;
    email: string | null;
    country: string;
    timezone: string;
    currency: string;
    locale: string;
    status: string;
};

export default function OrganizationSettings({
    organization,
    countries,
    currencies,
    locales,
    statuses,
    canUpdate,
    canChangeStatus,
}: {
    organization: OrganizationForm;
    countries: Record<string, string>;
    currencies: string[];
    locales: string[];
    statuses: string[];
    canUpdate: boolean;
    canChangeStatus: boolean;
}) {
    const { t } = useI18n();
    const form = useForm({
        name: organization.name,
        legal_name: organization.legal_name ?? '',
        tax_number: organization.tax_number ?? '',
        phone: organization.phone ?? '',
        email: organization.email ?? '',
        country: organization.country,
        timezone: organization.timezone,
        currency: organization.currency,
        locale: organization.locale,
        status: organization.status,
    });

    return (
        <>
            <PageHeader title={t.organizations.settings} subtitle={t.organizations.settingsSubtitle} />
            <form
                className="max-w-2xl space-y-4 rounded-lg border border-zinc-200 bg-white p-5"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put('/organization');
                }}
            >
                <FormField label={t.organizations.slug}>
                    <input className={controlClassName} value={organization.slug} disabled />
                </FormField>
                <FormField label={t.guest.organizationName} error={form.errors.name}>
                    <input className={controlClassName} value={form.data.name} disabled={!canUpdate} onChange={(event) => form.setData('name', event.target.value)} />
                </FormField>
                <FormField label={t.organizations.legalName} error={form.errors.legal_name}>
                    <input className={controlClassName} value={form.data.legal_name} disabled={!canUpdate} onChange={(event) => form.setData('legal_name', event.target.value)} />
                </FormField>
                <FormField label={t.organizations.taxNumber} error={form.errors.tax_number}>
                    <input className={controlClassName} value={form.data.tax_number} disabled={!canUpdate} onChange={(event) => form.setData('tax_number', event.target.value)} />
                </FormField>
                <FormField label={t.organizations.phone} error={form.errors.phone}>
                    <input className={controlClassName} value={form.data.phone} disabled={!canUpdate} onChange={(event) => form.setData('phone', event.target.value)} />
                </FormField>
                <FormField label={t.organizations.email} error={form.errors.email}>
                    <input className={controlClassName} type="email" value={form.data.email} disabled={!canUpdate} onChange={(event) => form.setData('email', event.target.value)} />
                </FormField>
                <FormField label={t.guest.country} error={form.errors.country}>
                    <select className={controlClassName} value={form.data.country} disabled={!canUpdate} onChange={(event) => form.setData('country', event.target.value)}>
                        {Object.entries(countries).map(([code, label]) => (
                            <option key={code} value={code}>
                                {label}
                            </option>
                        ))}
                    </select>
                </FormField>
                <FormField label={t.guest.timezone} error={form.errors.timezone}>
                    <input className={controlClassName} value={form.data.timezone} disabled={!canUpdate} onChange={(event) => form.setData('timezone', event.target.value)} />
                </FormField>
                <FormField label={t.guest.currency} error={form.errors.currency}>
                    <select className={controlClassName} value={form.data.currency} disabled={!canUpdate} onChange={(event) => form.setData('currency', event.target.value)}>
                        {currencies.map((currency) => (
                            <option key={currency} value={currency}>
                                {currency}
                            </option>
                        ))}
                    </select>
                </FormField>
                <FormField label={t.guest.locale} error={form.errors.locale}>
                    <select className={controlClassName} value={form.data.locale} disabled={!canUpdate} onChange={(event) => form.setData('locale', event.target.value)}>
                        {locales.map((locale) => (
                            <option key={locale} value={locale}>
                                {locale}
                            </option>
                        ))}
                    </select>
                </FormField>
                <FormField label={t.branches.status} error={form.errors.status}>
                    <select className={controlClassName} value={form.data.status} disabled={!canChangeStatus} onChange={(event) => form.setData('status', event.target.value)}>
                        {statuses.map((status) => (
                            <option key={status} value={status}>
                                {status}
                            </option>
                        ))}
                    </select>
                </FormField>
                {canUpdate ? (
                    <button type="submit" disabled={form.processing} className="rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white">
                        {t.organizations.save}
                    </button>
                ) : null}
            </form>
        </>
    );
}

OrganizationSettings.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
