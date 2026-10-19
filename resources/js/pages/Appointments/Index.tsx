import type { ReactNode } from 'react';
import { Button, ButtonLink } from '@/components/Button';
import { DataTable } from '@/components/DataTable';
import { FilterBar } from '@/components/FilterBar';
import { controlClassName } from '@/components/FormField';
import { PageHeader } from '@/components/PageHeader';
import { Pagination } from '@/components/Pagination';
import { RecordList } from '@/components/RecordList';
import { StatusBadge } from '@/components/StatusBadge';
import { useI18n } from '@/i18n';
import { optionLabel } from '@/lib/labels';
import AppLayout from '@/layouts/AppLayout';
import type { Option, Paginator } from '@/types/page';

type AppointmentRow = {
    id: string;
    scheduled_start: string;
    scheduled_end: string;
    status: string;
    customer: { name: string } | null;
    technician: { user: { name: string } | null } | null;
};

export default function Index({
    appointments,
    filters,
    technicians,
    branches,
    canManage,
}: {
    appointments: Paginator<AppointmentRow>;
    filters: { date?: string; status?: string; technician_id?: string; branch_id?: string };
    technicians: { id: string; name: string | null }[];
    branches: Option[];
    canManage: boolean;
}) {
    const { t } = useI18n();

    return (
        <>
            <PageHeader title={t.nav.appointments} action={canManage ? <ButtonLink href="/appointments/create">{t.ops.create}</ButtonLink> : null} />
            <FilterBar>
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
                    {['scheduled', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show'].map((value) => (
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
                <Button variant="primary" type="submit">
                    {t.common.search}
                </Button>
            </FilterBar>
            <RecordList empty={appointments.data.length === 0}>
                <DataTable headers={[t.ops.customer, t.ops.technician, t.ops.start, t.ops.end, t.ops.status, '']}>
                    {appointments.data.map((appointment) => (
                        <tr key={appointment.id}>
                            <td className="px-4 py-3 font-medium text-zinc-900">{appointment.customer?.name}</td>
                            <td className="px-4 py-3">{appointment.technician?.user?.name ?? t.common.none}</td>
                            <td className="px-4 py-3 tabular-nums">{appointment.scheduled_start}</td>
                            <td className="px-4 py-3 tabular-nums">{appointment.scheduled_end}</td>
                            <td className="px-4 py-3">
                                <StatusBadge value={appointment.status} />
                            </td>
                            <td className="px-4 py-3 text-end">
                                {canManage ? (
                                    <ButtonLink href={`/appointments/${appointment.id}/edit`} variant="ghost">
                                        {t.common.edit}
                                    </ButtonLink>
                                ) : null}
                            </td>
                        </tr>
                    ))}
                </DataTable>
            </RecordList>
            <Pagination links={appointments.links} />
        </>
    );
}

Index.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
