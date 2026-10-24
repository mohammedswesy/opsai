import type { ReactNode } from 'react';
import { ButtonLink } from '@/components/Button';
import { DataTable, type Column } from '@/components/DataTable';
import { FilterBar, activeFilters } from '@/components/FilterBar';
import { controlClassName } from '@/components/FormField';
import { PageHeader } from '@/components/PageHeader';
import { Pagination } from '@/components/Pagination';
import { PriorityBadge } from '@/components/PriorityBadge';
import { RecordList } from '@/components/RecordList';
import { SearchInput } from '@/components/SearchInput';
import { StatusBadge } from '@/components/StatusBadge';
import { useI18n } from '@/i18n';
import AppLayout from '@/layouts/AppLayout';
import { useFormat } from '@/lib/format';
import { optionLabel } from '@/lib/labels';
import { PRIORITIES, WORK_ORDER_STATUSES } from '@/lib/options';
import type { Option, Paginator } from '@/types/page';

type OrderRow = {
    id: string;
    title: string;
    status: string;
    priority: string;
    created_at: string | null;
    customer: { name: string } | null;
    technician: { user: { name: string } | null } | null;
    service_request: { subject: string } | null;
};

type Filters = { search?: string; status?: string; priority?: string; branch_id?: string };

export default function Index({
    workOrders,
    filters,
    branches,
    canManage,
}: {
    workOrders: Paginator<OrderRow>;
    filters: Filters;
    branches: Option[];
    canManage: boolean;
}) {
    const { t } = useI18n();
    const format = useFormat();
    const label = (value: string) => optionLabel(t.labels, value);
    const active = activeFilters(filters, {
        search: { label: t.common.search },
        status: { label: t.ops.status, value: label },
        priority: { label: t.ops.priority, value: label },
        branch_id: { label: t.ops.branch, value: (value) => branches.find((branch) => branch.id === value)?.name ?? value },
    });

    const columns: Column<OrderRow>[] = [
        {
            key: 'title',
            header: t.ops.title,
            primary: true,
            isolate: true,
            cell: (row) => (
                <>
                    <span className="block max-w-72 truncate" title={row.title}>
                        {row.title}
                    </span>
                    {row.service_request ? (
                        <span className="block max-w-72 truncate text-caption font-normal text-ink-muted" title={row.service_request.subject}>
                            {row.service_request.subject}
                        </span>
                    ) : null}
                </>
            ),
        },
        { key: 'customer', header: t.ops.customer, truncate: (row) => row.customer?.name, cell: (row) => row.customer?.name ?? t.common.none },
        { key: 'technician', header: t.ops.technician, truncate: (row) => row.technician?.user?.name, cell: (row) => row.technician?.user?.name ?? t.common.none },
        { key: 'priority', header: t.ops.priority, cell: (row) => <PriorityBadge value={row.priority} /> },
        { key: 'status', header: t.ops.status, cell: (row) => <StatusBadge value={row.status} /> },
        { key: 'created', header: t.list.created, cell: (row) => <span className="md:whitespace-nowrap tabular-nums">{row.created_at ? format.dateTime(row.created_at) : t.common.none}</span> },
    ];

    return (
        <>
            <PageHeader title={t.nav.workOrders} action={canManage ? <ButtonLink href="/work-orders/create">{t.ops.create}</ButtonLink> : null} />
            <FilterBar search={<SearchInput defaultValue={filters.search} placeholder={t.common.search} />} active={active}>
                <select className={controlClassName} name="status" defaultValue={filters.status ?? ''} aria-label={t.ops.status}>
                    <option value="">{t.ops.status}</option>
                    {WORK_ORDER_STATUSES.map((value) => (
                        <option key={value} value={value}>
                            {label(value)}
                        </option>
                    ))}
                </select>
                <select className={controlClassName} name="priority" defaultValue={filters.priority ?? ''} aria-label={t.ops.priority}>
                    <option value="">{t.ops.priority}</option>
                    {PRIORITIES.map((value) => (
                        <option key={value} value={value}>
                            {label(value)}
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
            <RecordList empty={workOrders.data.length === 0} filtered={active.length > 0} loading="inline">
                <DataTable
                    caption={t.nav.workOrders}
                    columns={columns}
                    rows={workOrders.data}
                    rowKey={(row) => row.id}
                    actions={(row) => [{ key: 'view', label: t.common.view, href: `/work-orders/${row.id}`, inline: true }]}
                />
                <Pagination paginator={workOrders} />
            </RecordList>
        </>
    );
}

Index.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
