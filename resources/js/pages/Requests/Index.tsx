import type { ReactNode } from 'react';
import { ButtonLink } from '@/components/Button';
import { Chip } from '@/components/Chip';
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
import { PRIORITIES, REQUEST_SOURCES, REQUEST_STATUSES } from '@/lib/options';
import type { Option, Paginator } from '@/types/page';

type RequestRow = {
    id: string;
    subject: string;
    status: string;
    priority: string;
    source: string;
    requested_at: string | null;
    customer: { name: string } | null;
    service: { name: string } | null;
};

type Filters = { search?: string; status?: string; priority?: string; source?: string; branch_id?: string };

export default function Index({
    requests,
    filters,
    branches,
    canManage,
}: {
    requests: Paginator<RequestRow>;
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
        source: { label: t.ops.source, value: label },
        branch_id: { label: t.ops.branch, value: (value) => branches.find((branch) => branch.id === value)?.name ?? value },
    });

    const columns: Column<RequestRow>[] = [
        {
            key: 'subject',
            header: t.ops.subject,
            primary: true,
            isolate: true,
            cell: (row) => (
                <>
                    <span className="block max-w-72 truncate" title={row.subject}>
                        {row.subject}
                    </span>
                    {row.service ? (
                        <span className="block max-w-72 truncate text-caption font-normal text-ink-muted" title={row.service.name}>
                            {row.service.name}
                        </span>
                    ) : null}
                </>
            ),
        },
        { key: 'customer', header: t.ops.customer, truncate: (row) => row.customer?.name, cell: (row) => row.customer?.name ?? t.common.none },
        { key: 'requested', header: t.ops.requestedAt, cell: (row) => <span className="md:whitespace-nowrap tabular-nums">{row.requested_at ? format.dateTime(row.requested_at) : t.common.none}</span> },
        { key: 'priority', header: t.ops.priority, cell: (row) => <PriorityBadge value={row.priority} /> },
        { key: 'status', header: t.ops.status, cell: (row) => <StatusBadge value={row.status} /> },
        { key: 'source', header: t.ops.source, cell: (row) => <Chip>{label(row.source)}</Chip> },
    ];

    return (
        <>
            <PageHeader title={t.nav.requests} action={canManage ? <ButtonLink href="/requests/create">{t.ops.create}</ButtonLink> : null} />
            <FilterBar search={<SearchInput defaultValue={filters.search} placeholder={t.common.search} />} active={active}>
                <select className={controlClassName} name="status" defaultValue={filters.status ?? ''} aria-label={t.ops.status}>
                    <option value="">{t.ops.status}</option>
                    {REQUEST_STATUSES.map((value) => (
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
                <select className={controlClassName} name="source" defaultValue={filters.source ?? ''} aria-label={t.ops.source}>
                    <option value="">{t.ops.source}</option>
                    {REQUEST_SOURCES.map((value) => (
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
            <RecordList empty={requests.data.length === 0} filtered={active.length > 0} loading="inline">
                <DataTable
                    caption={t.nav.requests}
                    columns={columns}
                    rows={requests.data}
                    rowKey={(row) => row.id}
                    actions={(row) => [{ key: 'view', label: t.common.view, href: `/requests/${row.id}`, inline: true }]}
                />
                <Pagination paginator={requests} />
            </RecordList>
        </>
    );
}

Index.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
