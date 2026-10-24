import { router, useForm } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { Button, ButtonLink } from '@/components/Button';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { DescriptionItem, DescriptionList } from '@/components/DescriptionList';
import { FormField, controlClassName } from '@/components/FormField';
import { PriorityBadge } from '@/components/PriorityBadge';
import { RecordHeader, type RecordMeta } from '@/components/RecordHeader';
import { Section } from '@/components/Section';
import { StatusBadge } from '@/components/StatusBadge';
import { ExternalTextLink, TextLink } from '@/components/TextLink';
import { useI18n } from '@/i18n';
import AppLayout from '@/layouts/AppLayout';
import { useFormat } from '@/lib/format';
import { optionLabel } from '@/lib/labels';
import { useCan } from '@/lib/permissions';

type Customer = {
    id: string;
    name: string;
    phone: string | null;
    whatsapp_number: string | null;
    email: string | null;
    address: string | null;
    city: string | null;
    country: string | null;
};

type WorkOrder = {
    id: string;
    title: string;
    description: string | null;
    priority: string;
    status: string;
    technician_notes: string | null;
    completion_notes: string | null;
    started_at: string | null;
    completed_at: string | null;
    created_at: string | null;
    customer: Customer | null;
    technician: { user: { name: string } | null } | null;
    service_request: { id: string; subject: string } | null;
    appointment: { id: string; scheduled_start: string; scheduled_end: string; status: string; notes: string | null } | null;
    branch: { name: string } | null;
};

export default function Show({
    workOrder,
    invoice,
    transitions,
    canTransition,
    canInvoice,
}: {
    workOrder: WorkOrder;
    invoice: { id: string; invoice_number: string } | null;
    transitions: string[];
    canTransition: boolean;
    canInvoice: boolean;
}) {
    const { t } = useI18n();
    const format = useFormat();
    const can = useCan();
    const form = useForm({ status: transitions[0] ?? '', notes: '' });
    const [confirming, setConfirming] = useState(false);
    const needsConfirm = form.data.status === 'completed' || form.data.status === 'cancelled';
    const customer = workOrder.customer;
    const technician = workOrder.technician?.user?.name ?? null;
    const appointment = workOrder.appointment;
    const visitTime = appointment
        ? `${format.dateTime(appointment.scheduled_start)} – ${format.sameDay(appointment.scheduled_start, appointment.scheduled_end) ? format.time(appointment.scheduled_end) : format.dateTime(appointment.scheduled_end)}`
        : null;
    const address = customer ? [customer.address, customer.city, customer.country].filter(Boolean).join(format.locale === 'ar' ? '، ' : ', ') : '';

    const meta: RecordMeta[] = [
        { key: 'customer', label: t.ops.customer, value: customer ? <bdi>{customer.name}</bdi> : t.common.none },
        { key: 'technician', label: t.ops.technician, value: technician ? <bdi>{technician}</bdi> : t.common.none },
    ];

    if (visitTime) {
        meta.push({ key: 'scheduled', label: t.detail.scheduled, value: <span className="tabular-nums">{visitTime}</span> });
    }

    return (
        <>
            <RecordHeader
                eyebrow={t.ops.relatedWorkOrder}
                title={workOrder.title}
                badges={
                    <>
                        <StatusBadge value={workOrder.status} />
                        <PriorityBadge value={workOrder.priority} />
                    </>
                }
                meta={meta}
                actions={
                    canInvoice && workOrder.status === 'completed' ? (
                        invoice ? (
                            <ButtonLink variant="primary" href={`/invoices/${invoice.id}`}>
                                {invoice.invoice_number}
                            </ButtonLink>
                        ) : (
                            <Button variant="primary" type="button" onClick={() => router.post(`/work-orders/${workOrder.id}/invoice`)}>
                                {t.finance.generateInvoice}
                            </Button>
                        )
                    ) : null
                }
            />

            <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <div className="min-w-0 space-y-5">
                    <Section title={t.detail.details}>
                        <DescriptionList>
                            <DescriptionItem label={t.ops.description} full isolate multiline>
                                {workOrder.description}
                            </DescriptionItem>
                            <DescriptionItem label={t.ops.relatedRequest}>
                                {workOrder.service_request ? (
                                    can('requests.view') ? (
                                        <TextLink href={`/requests/${workOrder.service_request.id}`}>
                                            <bdi>{workOrder.service_request.subject}</bdi>
                                        </TextLink>
                                    ) : (
                                        <bdi>{workOrder.service_request.subject}</bdi>
                                    )
                                ) : null}
                            </DescriptionItem>
                            <DescriptionItem label={t.ops.branch} isolate>
                                {workOrder.branch?.name}
                            </DescriptionItem>
                            <DescriptionItem label={t.detail.created}>{workOrder.created_at ? format.dateTime(workOrder.created_at) : null}</DescriptionItem>
                            <DescriptionItem label={t.detail.started}>{workOrder.started_at ? format.dateTime(workOrder.started_at) : null}</DescriptionItem>
                            <DescriptionItem label={t.detail.completedAt}>{workOrder.completed_at ? format.dateTime(workOrder.completed_at) : null}</DescriptionItem>
                        </DescriptionList>
                    </Section>

                    <Section title={t.detail.notes}>
                        <DescriptionList columns={1}>
                            <DescriptionItem label={t.detail.technicianNotes} isolate multiline>
                                {workOrder.technician_notes}
                            </DescriptionItem>
                            <DescriptionItem label={t.ops.completionNotes} isolate multiline>
                                {workOrder.completion_notes}
                            </DescriptionItem>
                        </DescriptionList>
                    </Section>

                    {canTransition && transitions.length > 0 ? (
                        <Section title={t.ops.transitions}>
                            <form
                                className="max-w-xl space-y-4"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    if (needsConfirm) {
                                        setConfirming(true);
                                        return;
                                    }
                                    form.post(`/work-orders/${workOrder.id}/transition`);
                                }}
                            >
                                <FormField label={t.ops.status} error={form.errors.status}>
                                    <select className={controlClassName} value={form.data.status} onChange={(event) => form.setData('status', event.target.value)}>
                                        {transitions.map((status) => (
                                            <option key={status} value={status}>
                                                {optionLabel(t.labels, status)}
                                            </option>
                                        ))}
                                    </select>
                                </FormField>
                                <FormField label={t.ops.completionNotes} error={form.errors.notes}>
                                    <textarea className={controlClassName} value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} />
                                </FormField>
                                <Button variant="primary" type="submit" disabled={form.processing}>
                                    {t.common.save}
                                </Button>
                            </form>
                        </Section>
                    ) : null}
                </div>

                <aside className="min-w-0 space-y-5">
                    <Section title={t.detail.site}>
                        {customer ? (
                            <DescriptionList columns={1}>
                                <DescriptionItem label={t.ops.customer}>
                                    {can('customers.view') ? (
                                        <TextLink href={`/customers/${customer.id}`}>
                                            <bdi>{customer.name}</bdi>
                                        </TextLink>
                                    ) : (
                                        <bdi>{customer.name}</bdi>
                                    )}
                                </DescriptionItem>
                                <DescriptionItem label={t.ops.phone} ltr>
                                    {customer.phone ? <ExternalTextLink href={`tel:${customer.phone}`}>{customer.phone}</ExternalTextLink> : null}
                                </DescriptionItem>
                                <DescriptionItem label={t.ops.whatsapp} ltr>
                                    {customer.whatsapp_number}
                                </DescriptionItem>
                                <DescriptionItem label={t.ops.address} isolate>
                                    {address}
                                </DescriptionItem>
                            </DescriptionList>
                        ) : (
                            <p className="text-body text-ink-muted">{t.common.none}</p>
                        )}
                    </Section>

                    <Section title={t.ops.relatedAppointment}>
                        {appointment ? (
                            <DescriptionList columns={1}>
                                <DescriptionItem label={t.detail.time}>
                                    <span className="tabular-nums">{visitTime}</span>
                                </DescriptionItem>
                                <DescriptionItem label={t.ops.status}>
                                    <StatusBadge value={appointment.status} />
                                </DescriptionItem>
                                <DescriptionItem label={t.common.notes} isolate multiline>
                                    {appointment.notes}
                                </DescriptionItem>
                            </DescriptionList>
                        ) : (
                            <p className="text-body text-ink-muted">{t.common.none}</p>
                        )}
                    </Section>
                </aside>
            </div>

            <ConfirmDialog
                open={confirming}
                title={t.ops.confirmStatus}
                confirmLabel={t.common.confirm}
                cancelLabel={t.common.cancel}
                onCancel={() => setConfirming(false)}
                onConfirm={() => {
                    setConfirming(false);
                    form.post(`/work-orders/${workOrder.id}/transition`);
                }}
            />
        </>
    );
}

Show.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
