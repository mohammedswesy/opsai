import { router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { ButtonLink } from '@/components/Button';
import { Chip } from '@/components/Chip';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { DataTable, type Column } from '@/components/DataTable';
import { FilterBar, activeFilters } from '@/components/FilterBar';
import { controlClassName } from '@/components/FormField';
import { PageHeader } from '@/components/PageHeader';
import { Pagination } from '@/components/Pagination';
import { RecordList } from '@/components/RecordList';
import { SearchInput } from '@/components/SearchInput';
import { StatusBadge } from '@/components/StatusBadge';
import { useI18n } from '@/i18n';
import AppLayout from '@/layouts/AppLayout';
import { optionLabel } from '@/lib/labels';
import { CUSTOMER_TYPES, RECORD_STATUSES } from '@/lib/options';
import type { Option, Paginator } from '@/types/page';

type CustomerRow = {
    id: string;
    name: string;
    company_name: string | null;
    phone: string;
    type: string;
    city: string | null;
    status: string;
    branch: { name: string } | null;
};

type Filters = { search?: string; status?: string; type?: string; branch_id?: string };

export default function Index({
    customers,
    filters,
    branches,
    canManage,
}: {
    customers: Paginator<CustomerRow>;
    filters: Filters;
    branches: Option[];
    canManage: boolean;
}) {
    const { t } = useI18n();
    const [archiveId, setArchiveId] = useState<string | null>(null);
    const active = activeFilters(filters, {
        search: { label: t.common.search },
        type: { label: t.ops.type, value: (value) => optionLabel(t.labels, value) },
        status: { label: t.ops.status, value: (value) => optionLabel(t.labels, value) },
        branch_id: { label: t.ops.branch, value: (value) => branches.find((branch) => branch.id === value)?.name ?? value },
    });

    const columns: Column<CustomerRow>[] = [
        {
            key: 'name',
            header: t.ops.customer,
            primary: true,
            isolate: true,
            cell: (row) => (
                <>
                    <span className="block max-w-64 truncate" title={row.name}>
                        {row.name}
                    </span>
                    {row.company_name ? (
                        <span className="block max-w-64 truncate text-caption font-normal text-ink-muted" title={row.company_name}>
                            {row.company_name}
                        </span>
                    ) : null}
                </>
            ),
        },
        { key: 'phone', header: t.ops.phone, cell: (row) => <bdi dir="ltr" className="tabular-nums">{row.phone}</bdi> },
        { key: 'type', header: t.ops.type, cell: (row) => <Chip>{optionLabel(t.labels, row.type)}</Chip> },
        { key: 'city', header: t.ops.city, truncate: (row) => row.city, cell: (row) => row.city ?? t.common.none },
        { key: 'branch', header: t.ops.branch, truncate: (row) => row.branch?.name, cell: (row) => row.branch?.name ?? t.common.none },
        { key: 'status', header: t.ops.status, cell: (row) => <StatusBadge value={row.status} /> },
    ];

    return (
        <>
            <PageHeader title={t.nav.customers} action={canManage ? <ButtonLink href="/customers/create">{t.ops.create}</ButtonLink> : null} />
            <FilterBar search={<SearchInput defaultValue={filters.search} placeholder={t.common.search} />} active={active}>
                <select className={controlClassName} name="type" defaultValue={filters.type ?? ''} aria-label={t.ops.type}>
                    <option value="">{t.ops.type}</option>
                    {CUSTOMER_TYPES.map((value) => (
                        <option key={value} value={value}>
                            {optionLabel(t.labels, value)}
                        </option>
                    ))}
                </select>
                <select className={controlClassName} name="status" defaultValue={filters.status ?? ''} aria-label={t.ops.status}>
                    <option value="">{t.ops.status}</option>
                    {RECORD_STATUSES.map((value) => (
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
            <RecordList empty={customers.data.length === 0} filtered={active.length > 0} loading="inline">
                <DataTable
                    caption={t.nav.customers}
                    columns={columns}
                    rows={customers.data}
                    rowKey={(row) => row.id}
                    actions={(row) => [
                        { key: 'view', label: t.common.view, href: `/customers/${row.id}`, inline: true },
                        ...(canManage ? [{ key: 'archive', label: t.common.archive, tone: 'danger' as const, onClick: () => setArchiveId(row.id) }] : []),
                    ]}
                />
                <Pagination paginator={customers} />
            </RecordList>
            <ConfirmDialog
                tone="danger"
                open={archiveId !== null}
                title={t.ops.archiveConfirm}
                confirmLabel={t.common.archive}
                cancelLabel={t.common.cancel}
                onCancel={() => setArchiveId(null)}
                onConfirm={() => {
                    if (archiveId) {
                        router.delete(`/customers/${archiveId}`);
                    }
                    setArchiveId(null);
                }}
            />
        </>
    );
}

Index.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
