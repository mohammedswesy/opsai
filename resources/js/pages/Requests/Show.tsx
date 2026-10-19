import { Card } from '@/components/Card';
import { Button, ButtonLink } from '@/components/Button';
import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { PageHeader } from '@/components/PageHeader';
import { PriorityBadge } from '@/components/PriorityBadge';
import { StatusBadge } from '@/components/StatusBadge';
import { useI18n } from '@/i18n';
import { optionLabel } from '@/lib/labels';
import AppLayout from '@/layouts/AppLayout';

export default function Show({
    serviceRequest,
    canManage,
}: {
    serviceRequest: {
        id: string;
        subject: string;
        description: string;
        priority: string;
        status: string;
        source: string;
        preferred_date: string | null;
        preferred_time: string | null;
        location: string | null;
        customer: { id: string; name: string } | null;
        service: { name: string } | null;
        appointments: { id: string; scheduled_start: string; status: string; technician: { user: { name: string } | null } | null }[];
        work_order: { id: string; title: string; status: string; technician: { user: { name: string } | null } | null } | null;
        activities: { id: string; action: string; description: string | null; actor: { name: string } | null }[];
    };
    canManage: boolean;
}) {
    const { t } = useI18n();
    const technician = serviceRequest.work_order?.technician?.user?.name ?? serviceRequest.appointments[0]?.technician?.user?.name;

    return (
        <>
            <PageHeader
                title={serviceRequest.subject}
                action={
                    canManage ? (
                        <ButtonLink variant="primary" href={`/requests/${serviceRequest.id}/edit`}>
                            {t.common.edit}
                        </ButtonLink>
                    ) : null
                }
            />
            <Card className="mb-6 grid gap-3 p-5 sm:grid-cols-2">
                <p>
                    {t.ops.customer}: {serviceRequest.customer ? <Link href={`/customers/${serviceRequest.customer.id}`}>{serviceRequest.customer.name}</Link> : t.common.none}
                </p>
                <p>
                    {t.ops.service}: {serviceRequest.service?.name ?? t.common.none}
                </p>
                <p>{serviceRequest.description}</p>
                <PriorityBadge value={serviceRequest.priority} />
                <StatusBadge value={serviceRequest.status} />
                <p>
                    {t.ops.source}: {optionLabel(t.labels, serviceRequest.source)}
                </p>
                <p>
                    {t.ops.preferredDate}: {serviceRequest.preferred_date ?? t.common.none} {serviceRequest.preferred_time ?? ''}
                </p>
                <p>
                    {t.ops.location}: {serviceRequest.location ?? t.common.none}
                </p>
                <p>
                    {t.ops.assignedTechnician}: {technician ?? t.common.none}
                </p>
            </Card>
            <Card className="mb-6 p-5 text-sm">
                <h2 className="mb-2 font-medium">{t.ops.relatedAppointment}</h2>
                {serviceRequest.appointments.length === 0 ? (
                    <p className="text-zinc-500">{t.common.none}</p>
                ) : (
                    serviceRequest.appointments.map((appointment) => (
                        <p key={appointment.id}>
                            {appointment.scheduled_start} <StatusBadge value={appointment.status} />
                        </p>
                    ))
                )}
            </Card>
            <Card className="mb-6 p-5 text-sm">
                <h2 className="mb-2 font-medium">{t.ops.relatedWorkOrder}</h2>
                {serviceRequest.work_order ? (
                    <Link href={`/work-orders/${serviceRequest.work_order.id}`} className="text-teal-800">
                        {serviceRequest.work_order.title}
                    </Link>
                ) : canManage ? (
                    <Link href={`/work-orders/create?service_request_id=${serviceRequest.id}&customer_id=${serviceRequest.customer?.id ?? ''}`} className="text-teal-800">
                        {t.ops.create}
                    </Link>
                ) : (
                    <p className="text-zinc-500">{t.common.none}</p>
                )}
            </Card>
            <Card className="p-5">
                <h2 className="mb-3 font-medium">{t.ops.activity}</h2>
                {serviceRequest.activities.length === 0 ? (
                    <p className="text-sm text-zinc-500">{t.ops.noActivity}</p>
                ) : (
                    <ul className="space-y-2 text-sm">
                        {serviceRequest.activities.map((activity) => (
                            <li key={activity.id}>
                                {activity.actor?.name ?? t.common.none} {activity.action} {activity.description}
                            </li>
                        ))}
                    </ul>
                )}
            </Card>
        </>
    );
}

Show.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
