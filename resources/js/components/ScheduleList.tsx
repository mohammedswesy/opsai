import { PriorityBadge } from '@/components/PriorityBadge';
import { StatusBadge } from '@/components/StatusBadge';
import { TextLink } from '@/components/TextLink';
import { useI18n } from '@/i18n';
import { useFormat } from '@/lib/format';
import { useCan } from '@/lib/permissions';

/** One appointment or work order row from the dashboard and technician schedule payloads. */
export type ScheduleItem = {
    type: 'appointment' | 'work_order';
    id: string;
    reference: string;
    title: string | null;
    start: string | null;
    end: string | null;
    status: string;
    priority: string | null;
    customer_name: string | null;
    technician_name: string | null;
    location: string | null;
    appointment_id: string | null;
    work_order_id: string | null;
};

/**
 * Time-ordered list of jobs. Each row links to its work order when there is one the
 * viewer can open, otherwise to the appointment's edit page. `showDate` adds the day for lists that
 * run past today; `showTechnician` names who is booked.
 */
export function ScheduleList({ items, empty, showDate = false, showTechnician = false }: { items: ScheduleItem[]; empty: string; showDate?: boolean; showTechnician?: boolean }) {
    const { t } = useI18n();
    const format = useFormat();
    const can = useCan();

    if (items.length === 0) {
        return <p className="text-body text-ink-muted">{empty}</p>;
    }

    const href = (item: ScheduleItem): string | null => {
        if (item.work_order_id && can('work_orders.view')) {
            return `/work-orders/${item.work_order_id}`;
        }

        // Appointments have no show page; only people who can change them get a link.
        return item.appointment_id && can('appointments.manage') ? `/appointments/${item.appointment_id}/edit` : null;
    };

    return (
        <ol className="divide-y divide-line">
            {items.map((item) => {
                const link = href(item);
                const title = item.title ?? item.reference;
                const when = item.start ? (showDate ? format.dateTime(item.start) : format.time(item.start)) : null;
                const details = [item.customer_name, showTechnician ? item.technician_name : null, item.location].filter(Boolean);

                return (
                    <li key={`${item.type}-${item.id}`} className="grid grid-cols-[auto_minmax(0,1fr)] items-start gap-x-3 gap-y-1.5 py-3 first:pt-0 last:pb-0 sm:grid-cols-[auto_minmax(0,1fr)_auto]">
                        <span className="row-span-2 w-24 pt-0.5 sm:row-span-1 text-caption font-medium text-ink-secondary tabular-nums sm:w-32">
                            {when ? <time dateTime={item.start ?? undefined}>{when}</time> : <span className="text-ink-subtle">{t.common.none}</span>}
                            {item.end && !showDate ? <span className="block font-normal text-ink-muted">{format.time(item.end)}</span> : null}
                        </span>
                        <div className="min-w-0">
                            <p className="text-body font-medium break-words text-ink">
                                {link ? (
                                    <TextLink href={link}>
                                        <bdi>{title}</bdi>
                                    </TextLink>
                                ) : (
                                    <bdi>{title}</bdi>
                                )}
                            </p>
                            {details.length > 0 ? (
                                <p className="text-caption break-words text-ink-muted">
                                    {details.map((detail, index) => (
                                        <span key={index}>
                                            {index > 0 ? ' · ' : null}
                                            <bdi>{detail}</bdi>
                                        </span>
                                    ))}
                                </p>
                            ) : null}
                        </div>
                        <div className="col-start-2 flex flex-wrap gap-1.5 sm:col-start-3 sm:row-start-1 sm:justify-end">
                            <StatusBadge value={item.status} />
                            {item.priority && item.priority !== 'normal' ? <PriorityBadge value={item.priority} /> : null}
                        </div>
                    </li>
                );
            })}
        </ol>
    );
}
