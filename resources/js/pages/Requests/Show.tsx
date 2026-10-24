import type { ReactNode } from 'react';
import { ActivityTimeline, type ActivityEntry } from '@/components/ActivityTimeline';
import { ButtonLink } from '@/components/Button';
import { DescriptionItem, DescriptionList } from '@/components/DescriptionList';
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

type Person = { user: { name: string } | null } | null;

type ServiceRequest = {
    id: string;
    subject: string;
    description: string;
    priority: string;
    status: string;
    source: string;
    requested_at: string | null;
    preferred_date: string | null;
    preferred_time: string | null;
    location: string | null;
    branch: { name: string } | null;
    customer: { id: string; name: string; phone: string | null; whatsapp_number: string | null; email: string | null } | null;
    service: { name: string } | null;
    appointments: { id: string; scheduled_start: string; scheduled_end: string; status: string; technician: Person }[];
    work_order: { id: string; title: string; status: string; technician: Person } | null;
    activities: ActivityEntry[];
};

export default function Show({ serviceRequest, canManage }: { serviceRequest: ServiceRequest; canManage: boolean }) {
    const { t } = useI18n();
    const format = useFormat();
    const can = useCan();
    const customer = serviceRequest.customer;
    const technician = serviceRequest.work_order?.technician?.user?.name ?? serviceRequest.appointments[0]?.technician?.user?.name ?? null;
    const preferred = [
        serviceRequest.preferred_date ? format.date(serviceRequest.preferred_date) : null,
        serviceRequest.preferred_time ? format.time(serviceRequest.preferred_time) : null,
    ]
        .filter(Boolean)
        .join(' · ');

    const meta: RecordMeta[] = [
        { key: 'customer', label: t.ops.customer, value: customer ? <bdi>{customer.name}</bdi> : t.common.none },
        { key: 'technician', label: t.ops.assignedTechnician, value: technician ?? t.common.none },
    ];

    if (serviceRequest.requested_at) {
        meta.push({ key: 'requested', label: t.detail.requested, value: <span className="tabular-nums">{format.dateTime(serviceRequest.requested_at)}</span> });
    }

    return (
        <>
            <RecordHeader parent={{ label: t.nav.requests, href: '/requests' }}
                eyebrow={t.ops.relatedRequest}
                title={serviceRequest.subject}
                badges={
                    <>
                        <StatusBadge value={serviceRequest.status} />
                        <PriorityBadge value={serviceRequest.priority} />
                    </>
                }
                meta={meta}
                actions={
                    canManage ? (
                        <ButtonLink variant="primary" href={`/requests/${serviceRequest.id}/edit`}>
                            {t.common.edit}
                        </ButtonLink>
                    ) : null
                }
            />

            <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <div className="min-w-0 space-y-5">
                    <Section title={t.detail.details}>
                        <DescriptionList>
                            <DescriptionItem label={t.ops.description} full isolate multiline>
                                {serviceRequest.description}
                            </DescriptionItem>
                            <DescriptionItem label={t.ops.service} isolate>
                                {serviceRequest.service?.name}
                            </DescriptionItem>
                            <DescriptionItem label={t.ops.source}>{optionLabel(t.labels, serviceRequest.source)}</DescriptionItem>
                            <DescriptionItem label={t.ops.preferredDate}>{preferred ? <span className="tabular-nums">{preferred}</span> : null}</DescriptionItem>
                            <DescriptionItem label={t.ops.location} isolate>
                                {serviceRequest.location}
                            </DescriptionItem>
                            <DescriptionItem label={t.ops.branch} isolate>
                                {serviceRequest.branch?.name}
                            </DescriptionItem>
                            <DescriptionItem label={t.detail.requested}>{serviceRequest.requested_at ? format.dateTime(serviceRequest.requested_at) : null}</DescriptionItem>
                        </DescriptionList>
                    </Section>

                    <Section title={t.ops.activity}>
                        <ActivityTimeline items={serviceRequest.activities} />
                    </Section>
                </div>

                <aside className="min-w-0 space-y-5">
                    <Section title={t.ops.customer}>
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
                                <DescriptionItem label={t.detail.email} ltr>
                                    {customer.email}
                                </DescriptionItem>
                            </DescriptionList>
                        ) : (
                            <p className="text-body text-ink-muted">{t.common.none}</p>
                        )}
                    </Section>

                    <Section title={t.ops.relatedAppointment}>
                        {serviceRequest.appointments.length === 0 ? (
                            <p className="text-body text-ink-muted">{t.common.none}</p>
                        ) : (
                            <ul className="divide-y divide-line">
                                {serviceRequest.appointments.map((appointment) => (
                                    <li key={appointment.id} className="py-2.5 first:pt-0 last:pb-0">
                                        <div className="flex items-start justify-between gap-3">
                                            <p className="text-body text-ink tabular-nums">
                                                {format.dateTime(appointment.scheduled_start)} –{' '}
                                                {format.sameDay(appointment.scheduled_start, appointment.scheduled_end) ? format.time(appointment.scheduled_end) : format.dateTime(appointment.scheduled_end)}
                                            </p>
                                            <StatusBadge value={appointment.status} />
                                        </div>
                                        <p className="mt-0.5 text-caption text-ink-muted">
                                            <bdi>{appointment.technician?.user?.name ?? t.common.none}</bdi>
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Section>

                    <Section title={t.ops.relatedWorkOrder}>
                        {serviceRequest.work_order ? (
                            <div className="space-y-1.5">
                                <div className="flex items-start justify-between gap-3">
                                    <TextLink href={`/work-orders/${serviceRequest.work_order.id}`} className="min-w-0 break-words">
                                        <bdi>{serviceRequest.work_order.title}</bdi>
                                    </TextLink>
                                    <StatusBadge value={serviceRequest.work_order.status} />
                                </div>
                                <p className="text-caption text-ink-muted">
                                    <bdi>{serviceRequest.work_order.technician?.user?.name ?? t.common.none}</bdi>
                                </p>
                            </div>
                        ) : canManage ? (
                            <TextLink href={`/work-orders/create?service_request_id=${serviceRequest.id}&customer_id=${customer?.id ?? ''}`}>{t.detail.createWorkOrder}</TextLink>
                        ) : (
                            <p className="text-body text-ink-muted">{t.common.none}</p>
                        )}
                    </Section>
                </aside>
            </div>
        </>
    );
}

Show.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
