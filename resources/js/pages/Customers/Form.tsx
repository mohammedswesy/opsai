import { Button } from '@/components/Button';
import { Card, CardForm } from '@/components/Card';
import { Link, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { FormField, controlClassName } from '@/components/FormField';
import { PageHeader } from '@/components/PageHeader';
import { useI18n } from '@/i18n';
import { optionLabel } from '@/lib/labels';
import AppLayout from '@/layouts/AppLayout';
import { REQUEST_SOURCES } from '@/lib/options';
import type { Option } from '@/types/page';

type Customer = {
    id: string;
    type: string;
    name: string;
    company_name: string | null;
    email: string | null;
    phone: string;
    whatsapp_number: string | null;
    address: string | null;
    city: string | null;
    country: string | null;
    notes: string | null;
    source: string | null;
    status: string;
    branch_id: string | null;
};

export default function Form({ customer, branches }: { customer: Customer | null; branches: Option[] }) {
    const { t } = useI18n();
    const form = useForm({
        branch_id: customer?.branch_id ?? '',
        type: customer?.type ?? 'individual',
        name: customer?.name ?? '',
        company_name: customer?.company_name ?? '',
        email: customer?.email ?? '',
        phone: customer?.phone ?? '',
        whatsapp_number: customer?.whatsapp_number ?? '',
        address: customer?.address ?? '',
        city: customer?.city ?? '',
        country: customer?.country ?? '',
        notes: customer?.notes ?? '',
        source: customer?.source ?? 'manual',
        status: customer?.status ?? 'active',
    });

    return (
        <>
            <PageHeader parent={{ label: t.nav.customers, href: '/customers' }} title={customer ? t.common.edit : t.ops.create} subtitle={t.nav.customers} />
            <CardForm className="grid max-w-3xl gap-4 p-5 md:grid-cols-2" onSubmit={(event) => {
                    event.preventDefault();
                    const payload = { ...form.data, branch_id: form.data.branch_id || null };
                    form.transform(() => payload);
                    if (customer) {
                        form.put(`/customers/${customer.id}`);
                    } else {
                        form.post('/customers');
                    }
                }}
            >
                <FormField label={t.ops.type} error={form.errors.type}>
                    <select className={controlClassName} value={form.data.type} onChange={(event) => form.setData('type', event.target.value)}>
                        <option value="individual">{t.labels.individual}</option>
                        <option value="company">{t.labels.company}</option>
                    </select>
                </FormField>
                <FormField label={t.ops.customer} error={form.errors.name}>
                    <input className={controlClassName} value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />
                </FormField>
                <FormField label={t.ops.company} error={form.errors.company_name}>
                    <input className={controlClassName} value={form.data.company_name} onChange={(event) => form.setData('company_name', event.target.value)} />
                </FormField>
                <FormField label={t.ops.phone} error={form.errors.phone}>
                    <input className={controlClassName} value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} />
                </FormField>
                <FormField label={t.ops.whatsapp} error={form.errors.whatsapp_number}>
                    <input className={controlClassName} value={form.data.whatsapp_number} onChange={(event) => form.setData('whatsapp_number', event.target.value)} />
                </FormField>
                <FormField label={t.guest.email} error={form.errors.email}>
                    <input className={controlClassName} value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} />
                </FormField>
                <FormField label={t.ops.city} error={form.errors.city}>
                    <input className={controlClassName} value={form.data.city} onChange={(event) => form.setData('city', event.target.value)} />
                </FormField>
                <FormField label={t.ops.country} error={form.errors.country}>
                    <input className={controlClassName} value={form.data.country} onChange={(event) => form.setData('country', event.target.value)} />
                </FormField>
                <FormField label={t.ops.address} error={form.errors.address}>
                    <input className={controlClassName} value={form.data.address} onChange={(event) => form.setData('address', event.target.value)} />
                </FormField>
                <FormField label={t.ops.branch} error={form.errors.branch_id}>
                    <select className={controlClassName} value={form.data.branch_id} onChange={(event) => form.setData('branch_id', event.target.value)}>
                        <option value="">{t.common.none}</option>
                        {branches.map((branch) => (
                            <option key={branch.id} value={branch.id}>
                                {branch.name}
                            </option>
                        ))}
                    </select>
                </FormField>
                <FormField label={t.ops.source} error={form.errors.source}>
                    <select className={controlClassName} value={form.data.source} onChange={(event) => form.setData('source', event.target.value)}>
                        {REQUEST_SOURCES.map((source) => (
                            <option key={source} value={source}>
                                {optionLabel(t.labels, source)}
                            </option>
                        ))}
                    </select>
                </FormField>
                <FormField label={t.ops.status} error={form.errors.status}>
                    <select className={controlClassName} value={form.data.status} onChange={(event) => form.setData('status', event.target.value)}>
                        <option value="active">{t.labels.active}</option>
                        <option value="inactive">{t.labels.inactive}</option>
                    </select>
                </FormField>
                <FormField label={t.common.notes} error={form.errors.notes}>
                    <textarea className={controlClassName} value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} />
                </FormField>
                <div className="flex gap-3 md:col-span-2">
                    <Button variant="primary" type="submit" disabled={form.processing}>
                        {t.common.save}
                    </Button>
                    <Link href="/customers" className="rounded-md px-4 py-2 text-sm text-zinc-700">
                        {t.common.cancel}
                    </Link>
                </div>
            </CardForm>
        </>
    );
}

Form.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
