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
import { APPOINTMENT_STATUSES } from '@/lib/options';
import type { Option } from '@/types/page';

type AppointmentForm = {
    id: string;
    customer_id: string;
    technician_id: string;
    service_request_id: string | null;
    branch_id: string | null;
    scheduled_start: string;
    scheduled_end: string;
    status: string;
    notes: string | null;
};

export default function Form({
    appointment,
    branches,
    customers,
    technicians,
    requests,
}: {
    appointment: AppointmentForm | null;
    branches: Option[];
    customers: Option[];
    technicians: { id: string; name: string | null }[];
    requests: { id: string; subject: string }[];
}) {
    const { t } = useI18n();
    const form = useForm({
        branch_id: appointment?.branch_id ?? '',
        customer_id: appointment?.customer_id ?? '',
        technician_id: appointment?.technician_id ?? '',
        service_request_id: appointment?.service_request_id ?? '',
        scheduled_start: appointment?.scheduled_start ?? '',
        scheduled_end: appointment?.scheduled_end ?? '',
        status: appointment?.status ?? 'scheduled',
        notes: appointment?.notes ?? '',
    });

    return (
        <FormPage>
            <PageHeader parent={{ label: t.nav.appointments, href: '/appointments' }} title={appointment ? t.common.edit : t.ops.create} subtitle={t.nav.appointments} />
            <CardForm className="grid gap-4 p-5" onSubmit={(event) => {
                    event.preventDefault();
                    const payload = {
                        ...form.data,
                        branch_id: form.data.branch_id || null,
                        service_request_id: form.data.service_request_id || null,
                    };
                    form.transform(() => payload);
                    if (appointment) {
                        form.put(`/appointments/${appointment.id}`);
                    } else {
                        form.post('/appointments');
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
                <FormField label={t.ops.relatedRequest} error={form.errors.service_request_id}>
                    <select className={controlClassName} value={form.data.service_request_id} onChange={(event) => form.setData('service_request_id', event.target.value)}>
                        <option value="">{t.common.none}</option>
                        {requests.map((request) => (
                            <option key={request.id} value={request.id}>
                                {request.subject}
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
                <FormField label={t.ops.start} error={form.errors.scheduled_start}>
                    <input type="datetime-local" className={controlClassName} value={form.data.scheduled_start} onChange={(event) => form.setData('scheduled_start', event.target.value)} />
                </FormField>
                <FormField label={t.ops.end} error={form.errors.scheduled_end}>
                    <input type="datetime-local" className={controlClassName} value={form.data.scheduled_end} onChange={(event) => form.setData('scheduled_end', event.target.value)} />
                </FormField>
                <FormField label={t.ops.status} error={form.errors.status}>
                    <select className={controlClassName} value={form.data.status} onChange={(event) => form.setData('status', event.target.value)}>
                        {APPOINTMENT_STATUSES.map((value) => (
                            <option key={value} value={value}>
                                {optionLabel(t.labels, value)}
                            </option>
                        ))}
                    </select>
                </FormField>
                <FormField label={t.common.notes} error={form.errors.notes}>
                    <textarea className={controlClassName} value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} />
                </FormField>
                <div className="flex gap-3">
                    <Button variant="primary" type="submit" disabled={form.processing}>
                        {t.common.save}
                    </Button>
                    <Link href="/appointments" className="px-4 py-2 text-sm text-zinc-700">
                        {t.common.cancel}
                    </Link>
                </div>
            </CardForm>
        </FormPage>
    );
}

Form.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
