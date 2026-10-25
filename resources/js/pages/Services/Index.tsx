import { router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { ButtonLink } from '@/components/Button';
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
import { useFormat } from '@/lib/format';
import { optionLabel } from '@/lib/labels';
import { RECORD_STATUSES } from '@/lib/options';
import type { Paginator } from '@/types/page';

type ServiceRow = {
    id: string;
    name: string;
    category: string | null;
    base_price: string | null;
    estimated_duration: number | null;
    status: string;
};

type Filters = { search?: string; status?: string; category?: string };

export default function Index({ services, filters, canManage }: { services: Paginator<ServiceRow>; filters: Filters; canManage: boolean }) {
    const { t } = useI18n();
    const format = useFormat();
    const [archiveId, setArchiveId] = useState<string | null>(null);
    const active = activeFilters(filters, {
        search: { label: t.common.search },
        category: { label: t.ops.category },
        status: { label: t.ops.status, value: (value) => optionLabel(t.labels, value) },
    });

    const columns: Column<ServiceRow>[] = [
        { key: 'name', header: t.ops.service, primary: true, truncate: (row) => row.name, cell: (row) => row.name },
        { key: 'category', header: t.ops.category, truncate: (row) => row.category, cell: (row) => row.category ?? t.common.none },
        { key: 'price', header: t.ops.price, numeric: true, cell: (row) => (row.base_price !== null ? format.money(row.base_price) : t.common.none) },
        { key: 'duration', header: t.ops.estimatedDuration, numeric: true, cell: (row) => (row.estimated_duration !== null ? format.duration(row.estimated_duration) : t.common.none) },
        { key: 'status', header: t.ops.status, cell: (row) => <StatusBadge value={row.status} /> },
    ];

    return (
        <>
            <PageHeader
                title={t.nav.services}
                action={
                    canManage ? (
                        <ButtonLink variant="primary" href="/services/create">
                            {t.ops.create}
                        </ButtonLink>
                    ) : null
                }
            />
            <FilterBar search={<SearchInput defaultValue={filters.search} placeholder={t.common.search} />} active={active}>
                <input className={controlClassName} name="category" defaultValue={filters.category ?? ''} placeholder={t.ops.category} aria-label={t.ops.category} />
                <select className={controlClassName} name="status" defaultValue={filters.status ?? ''} aria-label={t.ops.status}>
                    <option value="">{t.ops.status}</option>
                    {RECORD_STATUSES.map((value) => (
                        <option key={value} value={value}>
                            {optionLabel(t.labels, value)}
                        </option>
                    ))}
                </select>
            </FilterBar>
            <RecordList empty={services.data.length === 0} filtered={active.length > 0} loading="inline">
                <DataTable
                    caption={t.nav.services}
                    columns={columns}
                    rows={services.data}
                    rowKey={(row) => row.id}
                    actions={
                        canManage
                            ? (row) => [
                                  { key: 'edit', label: t.common.edit, href: `/services/${row.id}/edit`, inline: true },
                                  { key: 'archive', label: t.common.archive, tone: 'danger', onClick: () => setArchiveId(row.id) },
                              ]
                            : undefined
                    }
                />
                <Pagination paginator={services} />
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
                        router.delete(`/services/${archiveId}`);
                    }
                    setArchiveId(null);
                }}
            />
        </>
    );
}

Index.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
