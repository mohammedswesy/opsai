import { Button } from '@/components/Button';
import { Card, CardForm } from '@/components/Card';
import { Link, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { FormField, controlClassName } from '@/components/FormField';
import { FormPage } from '@/components/FormPage';
import { PageHeader } from '@/components/PageHeader';
import { useI18n } from '@/i18n';
import { optionLabel } from '@/lib/labels';
import AppLayout from '@/layouts/AppLayout';
import { PRIORITIES, REQUEST_SOURCES, REQUEST_STATUSES } from '@/lib/options';
import type { Option } from '@/types/page';

type RequestForm = {
    id: string;
    branch_id: string | null;
    customer_id: string;
    service_id: string | null;
    source: string;
    subject: string;
    description: string;
    priority: string;
    status: string;
    preferred_date: string | null;
    preferred_time: string | null;
    location: string | null;
};

export default function Form({
    serviceRequest,
    branches,
    customers,
    services,
}: {
    serviceRequest: RequestForm | null;
    branches: Option[];
    customers: Option[];
    services: Option[];
}) {
    const { t } = useI18n();
    const form = useForm({
        branch_id: serviceRequest?.branch_id ?? '',
        customer_id: serviceRequest?.customer_id ?? '',
        service_id: serviceRequest?.service_id ?? '',
        source: serviceRequest?.source ?? 'manual',
        subject: serviceRequest?.subject ?? '',
        description: serviceRequest?.description ?? '',
        priority: serviceRequest?.priority ?? 'normal',
        status: serviceRequest?.status ?? 'new',
        preferred_date: serviceRequest?.preferred_date?.slice(0, 10) ?? '',
        preferred_time: serviceRequest?.preferred_time?.slice(0, 5) ?? '',
        location: serviceRequest?.location ?? '',
    });

    return (
        <FormPage size="wide">
            <PageHeader parent={{ label: t.nav.requests, href: '/requests' }} title={serviceRequest ? t.common.edit : t.ops.create} subtitle={t.nav.requests} />
            <CardForm className="grid gap-4 p-5 md:grid-cols-2" onSubmit={(event) => {
                    event.preventDefault();
                    const payload = {
                        ...form.data,
                        branch_id: form.data.branch_id || null,
                        service_id: form.data.service_id || null,
                        preferred_date: form.data.preferred_date || null,
                        preferred_time: form.data.preferred_time || null,
                    };
                    form.transform(() => payload);
                    if (serviceRequest) {
                        form.put(`/requests/${serviceRequest.id}`);
                    } else {
                        form.post('/requests');
                    }
                }}
            >
                <FormField label={t.ops.customer} error={form.errors.customer_id}>
                    <select className={controlClassName} value={form.data.customer_id} onChange={(event) => form.setData('customer_id', event.target.value)}>
                        <option value="">{t.common.none}</option>
                        {customers.map((customer) => (
                            <option key={customer.id} value={customer.id}>
                                {customer.name}
                            </option>
                        ))}
                    </select>
                </FormField>
                <FormField label={t.ops.service} error={form.errors.service_id}>
                    <select className={controlClassName} value={form.data.service_id} onChange={(event) => form.setData('service_id', event.target.value)}>
                        <option value="">{t.common.none}</option>
                        {services.map((service) => (
                            <option key={service.id} value={service.id}>
                                {service.name}
                            </option>
                        ))}
                    </select>
                </FormField>
                <FormField label={t.ops.subject} error={form.errors.subject}>
                    <input className={controlClassName} value={form.data.subject} onChange={(event) => form.setData('subject', event.target.value)} />
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
                <FormField label={t.ops.description} error={form.errors.description}>
                    <textarea className={controlClassName} value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} />
                </FormField>
                <FormField label={t.ops.priority} error={form.errors.priority}>
                    <select className={controlClassName} value={form.data.priority} onChange={(event) => form.setData('priority', event.target.value)}>
                        {PRIORITIES.map((value) => (
                            <option key={value} value={value}>
                                {optionLabel(t.labels, value)}
                            </option>
                        ))}
                    </select>
                </FormField>
                <FormField label={t.ops.status} error={form.errors.status}>
                    <select className={controlClassName} value={form.data.status} onChange={(event) => form.setData('status', event.target.value)}>
                        {REQUEST_STATUSES.map((value) => (
                            <option key={value} value={value}>
                                {optionLabel(t.labels, value)}
                            </option>
                        ))}
                    </select>
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
                <FormField label={t.ops.preferredDate} error={form.errors.preferred_date}>
                    <input type="date" className={controlClassName} value={form.data.preferred_date} onChange={(event) => form.setData('preferred_date', event.target.value)} />
                </FormField>
                <FormField label={t.ops.preferredTime} error={form.errors.preferred_time}>
                    <input type="time" className={controlClassName} value={form.data.preferred_time} onChange={(event) => form.setData('preferred_time', event.target.value)} />
                </FormField>
                <FormField label={t.ops.location} error={form.errors.location}>
                    <input className={controlClassName} value={form.data.location} onChange={(event) => form.setData('location', event.target.value)} />
                </FormField>
                <div className="flex gap-3 md:col-span-2">
                    <Button variant="primary" type="submit" disabled={form.processing}>
                        {t.common.save}
                    </Button>
                    <Link href="/requests" className="px-4 py-2 text-sm text-zinc-700">
                        {t.common.cancel}
                    </Link>
                </div>
            </CardForm>
        </FormPage>
    );
}

Form.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
