import type { ReactNode } from 'react';
import { ButtonLink } from '@/components/Button';
import { DataTable, type Column } from '@/components/DataTable';
import { FilterBar, activeFilters } from '@/components/FilterBar';
import { controlClassName } from '@/components/FormField';
import { PageHeader } from '@/components/PageHeader';
import { Pagination } from '@/components/Pagination';
import { RecordList } from '@/components/RecordList';
import { StatusBadge } from '@/components/StatusBadge';
import { useI18n } from '@/i18n';
import AppLayout from '@/layouts/AppLayout';
import { useFormat } from '@/lib/format';
import { optionLabel } from '@/lib/labels';
import { APPOINTMENT_STATUSES } from '@/lib/options';
import type { Option, Paginator } from '@/types/page';

type AppointmentRow = {
    id: string;
    scheduled_start: string;
    scheduled_end: string;
    status: string;
    customer: { name: string } | null;
    technician: { user: { name: string } | null } | null;
    service_request: { subject: string } | null;
};

type Filters = { date?: string; status?: string; technician_id?: string; branch_id?: string };

export default function Index({
    appointments,
    filters,
    technicians,
    branches,
    canManage,
}: {
    appointments: Paginator<AppointmentRow>;
    filters: Filters;
    technicians: { id: string; name: string | null }[];
    branches: Option[];
    canManage: boolean;
}) {
    const { t } = useI18n();
    const format = useFormat();
    const active = activeFilters(filters, {
        date: { label: t.ops.date, value: (value) => format.date(value) },
        technician_id: { label: t.ops.technician, value: (value) => technicians.find((technician) => technician.id === value)?.name ?? value },
        status: { label: t.ops.status, value: (value) => optionLabel(t.labels, value) },
        branch_id: { label: t.ops.branch, value: (value) => branches.find((branch) => branch.id === value)?.name ?? value },
    });

    const columns: Column<AppointmentRow>[] = [
        { key: 'customer', header: t.ops.customer, primary: true, truncate: (row) => row.customer?.name, cell: (row) => row.customer?.name ?? t.common.none },
        { key: 'technician', header: t.ops.technician, truncate: (row) => row.technician?.user?.name, cell: (row) => row.technician?.user?.name ?? t.common.none },
        {
            key: 'scheduled',
            header: t.list.scheduled,
            cell: (row) => (
                <span className="md:whitespace-nowrap tabular-nums">
                    {format.dateTime(row.scheduled_start)} – {format.sameDay(row.scheduled_start, row.scheduled_end) ? format.time(row.scheduled_end) : format.dateTime(row.scheduled_end)}
                </span>
            ),
        },
        {
            key: 'request',
            header: t.ops.relatedRequest,
            truncate: (row) => row.service_request?.subject,
            cell: (row) => row.service_request?.subject ?? <span className="text-ink-muted">{t.common.none}</span>,
        },
        { key: 'status', header: t.ops.status, cell: (row) => <StatusBadge value={row.status} /> },
    ];

    return (
        <>
            <PageHeader title={t.nav.appointments} action={canManage ? <ButtonLink href="/appointments/create">{t.ops.create}</ButtonLink> : null} />
            <FilterBar active={active}>
                <input className={controlClassName} type="date" name="date" defaultValue={filters.date ?? ''} aria-label={t.ops.date} />
                <select className={controlClassName} name="technician_id" defaultValue={filters.technician_id ?? ''} aria-label={t.ops.technician}>
                    <option value="">{t.ops.technician}</option>
                    {technicians.map((technician) => (
                        <option key={technician.id} value={technician.id}>
                            {technician.name}
                        </option>
                    ))}
                </select>
                <select className={controlClassName} name="status" defaultValue={filters.status ?? ''} aria-label={t.ops.status}>
                    <option value="">{t.ops.status}</option>
                    {APPOINTMENT_STATUSES.map((value) => (
                        <option key={value} value={value}>
                            {optionLabel(t.labels, value)}
                        </option>
                    ))}
                </select>
                <select className={controlClassName} name="branch_id" defaultValue={filters.branch_id ?? ''} aria-label={t.ops.branch}>
                    <option value="">{t.ops.branch}</option>
                    {branches.map((branch) => (
                        <option key={branch.id} value={branch.id}>
                            {branch.name}
                        </option>
                    ))}
                </select>
            </FilterBar>
            <RecordList empty={appointments.data.length === 0} filtered={active.length > 0} loading="inline">
                <DataTable
                    caption={t.nav.appointments}
                    columns={columns}
                    rows={appointments.data}
                    rowKey={(row) => row.id}
                    actions={canManage ? (row) => [{ key: 'edit', label: t.common.edit, href: `/appointments/${row.id}/edit`, inline: true }] : undefined}
                />
                <Pagination paginator={appointments} />
            </RecordList>
        </>
    );
}

Index.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
