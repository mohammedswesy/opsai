import type { ReactNode } from 'react';
import { ButtonLink } from '@/components/Button';
import { Chip } from '@/components/Chip';
import { DataTable, type Column } from '@/components/DataTable';
import { FilterBar, activeFilters } from '@/components/FilterBar';
import { PageHeader } from '@/components/PageHeader';
import { Pagination } from '@/components/Pagination';
import { RecordList } from '@/components/RecordList';
import { SearchInput } from '@/components/SearchInput';
import { StatusBadge } from '@/components/StatusBadge';
import { useI18n } from '@/i18n';
import AppLayout from '@/layouts/AppLayout';
import { useFormat } from '@/lib/format';
import { optionLabel } from '@/lib/labels';
import type { Paginator } from '@/types/page';

type PaymentRow = {
    id: string;
    amount: string;
    currency: string;
    method: string;
    status: string;
    reference: string | null;
    paid_at: string;
    invoice: { invoice_number: string } | null;
};

export default function Index({ payments, filters, canManage }: { payments: Paginator<PaymentRow>; filters: { search?: string }; canManage: boolean }) {
    const { t } = useI18n();
    const format = useFormat();
    const active = activeFilters(filters, { search: { label: t.common.search } });

    const columns: Column<PaymentRow>[] = [
        {
            key: 'invoice',
            header: t.finance.invoiceNumber,
            primary: true,
            cell: (row) => (row.invoice ? <bdi dir="ltr" className="md:whitespace-nowrap">{row.invoice.invoice_number}</bdi> : t.common.none),
        },
        { key: 'amount', header: t.finance.amount, numeric: true, cell: (row) => <span className="font-medium text-ink">{format.money(row.amount, row.currency)}</span> },
        { key: 'paid', header: t.finance.paidAt, cell: (row) => <span className="md:whitespace-nowrap tabular-nums">{format.dateTime(row.paid_at)}</span> },
        { key: 'method', header: t.finance.method, cell: (row) => <Chip>{optionLabel(t.labels, row.method)}</Chip> },
        { key: 'status', header: t.ops.status, cell: (row) => <StatusBadge value={row.status} /> },
        {
            key: 'reference',
            header: t.finance.reference,
            cell: (row) =>
                row.reference ? (
                    <bdi dir="ltr" className="inline-block max-w-40 truncate align-middle" title={row.reference}>
                        {row.reference}
                    </bdi>
                ) : (
                    <span className="text-ink-subtle">{t.common.none}</span>
                ),
        },
    ];

    return (
        <>
            <PageHeader
                title={t.nav.payments}
                action={
                    canManage ? (
                        <ButtonLink variant="primary" href="/payments/create">
                            {t.finance.recordPayment}
                        </ButtonLink>
                    ) : null
                }
            />
            <FilterBar search={<SearchInput defaultValue={filters.search} placeholder={t.common.search} />} active={active} />
            <RecordList empty={payments.data.length === 0} filtered={active.length > 0} loading="inline">
                <DataTable
                    caption={t.nav.payments}
                    columns={columns}
                    rows={payments.data}
                    rowKey={(row) => row.id}
                    actions={(row) => [{ key: 'view', label: t.common.view, href: `/payments/${row.id}`, inline: true }]}
                />
                <Pagination paginator={payments} />
            </RecordList>
        </>
    );
}

Index.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
