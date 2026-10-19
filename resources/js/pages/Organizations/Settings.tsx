import { Button } from '@/components/Button';
import { Card, CardForm } from '@/components/Card';
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
    tax_rate: string;
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
        tax_rate: organization.tax_rate ?? '',
    });

    return (
        <>
            <PageHeader title={t.organizations.settings} subtitle={t.organizations.settingsSubtitle} />
            <CardForm className="max-w-2xl space-y-4 p-5" onSubmit={(event) => {
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
                <FormField label={t.finance.taxRate} error={form.errors.tax_rate}>
                    <input className={controlClassName} value={form.data.tax_rate} disabled={!canUpdate} onChange={(event) => form.setData('tax_rate', event.target.value)} />
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
                    <Button variant="primary" type="submit" disabled={form.processing}>
                        {t.organizations.save}
                    </Button>
                ) : null}
            </CardForm>
        </>
    );
}

OrganizationSettings.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
