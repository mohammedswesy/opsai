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
import { PRIORITIES } from '@/lib/options';
import type { Option } from '@/types/page';

export default function Form({
    branches,
    customers,
    technicians,
    requests,
    defaults,
}: {
    branches: Option[];
    customers: Option[];
    technicians: { id: string; name: string | null }[];
    requests: { id: string; subject: string; customer_id: string }[];
    defaults: { service_request_id: string; customer_id: string; appointment_id: string };
}) {
    const { t } = useI18n();
    const form = useForm({
        branch_id: '',
        customer_id: defaults.customer_id,
        service_request_id: defaults.service_request_id,
        appointment_id: defaults.appointment_id,
        technician_id: '',
        title: '',
        description: '',
        priority: 'normal',
    });

    return (
        <FormPage>
            <PageHeader parent={{ label: t.nav.workOrders, href: '/work-orders' }} title={t.ops.create} subtitle={t.nav.workOrders} />
            <CardForm className="grid gap-4 p-5" onSubmit={(event) => {
                    event.preventDefault();
                    form.transform((data) => ({
                        ...data,
                        branch_id: data.branch_id || null,
                        technician_id: data.technician_id || null,
                        appointment_id: data.appointment_id || null,
                    }));
                    form.post('/work-orders');
                }}
            >
                <FormField label={t.ops.relatedRequest} error={form.errors.service_request_id}>
                    <select
                        className={controlClassName}
                        value={form.data.service_request_id}
                        onChange={(event) => {
                            const request = requests.find((item) => item.id === event.target.value);
                            form.setData({
                                ...form.data,
                                service_request_id: event.target.value,
                                customer_id: request?.customer_id ?? form.data.customer_id,
                            });
                        }}
                    >
                        <option value="">{t.common.none}</option>
                        {requests.map((request) => (
                            <option key={request.id} value={request.id}>
                                {request.subject}
                            </option>
                        ))}
                    </select>
                </FormField>
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
                <FormField label={t.ops.technician} error={form.errors.technician_id}>
                    <select className={controlClassName} value={form.data.technician_id} onChange={(event) => form.setData('technician_id', event.target.value)}>
                        <option value="">{t.common.none}</option>
                        {technicians.map((technician) => (
                            <option key={technician.id} value={technician.id}>
                                {technician.name}
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
                <FormField label={t.ops.title} error={form.errors.title}>
                    <input className={controlClassName} value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} />
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
                <div className="flex gap-3">
                    <Button variant="primary" type="submit" disabled={form.processing}>
                        {t.common.save}
                    </Button>
                    <Link href="/work-orders" className="px-4 py-2 text-sm text-zinc-700">
                        {t.common.cancel}
                    </Link>
                </div>
            </CardForm>
        </FormPage>
    );
}

Form.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
