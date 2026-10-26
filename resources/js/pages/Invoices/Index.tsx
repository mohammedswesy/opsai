import type { ReactNode } from 'react';
import { ButtonLink } from '@/components/Button';
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
import { INVOICE_STATUSES } from '@/lib/options';
import type { Option, Paginator } from '@/types/page';

type InvoiceRow = {
    id: string;
    invoice_number: string;
    status: string;
    issue_date: string;
    due_date: string | null;
    currency: string;
    total: string;
    amount_due: string;
    customer: { name: string } | null;
};

type Filters = { search?: string; status?: string; customer_id?: string; from?: string; to?: string; overdue?: string };

export default function Index({
    invoices,
    filters,
    customers,
    canManage,
}: {
    invoices: Paginator<InvoiceRow>;
    filters: Filters;
    customers: Option[];
    canManage: boolean;
}) {
    const { t } = useI18n();
    const format = useFormat();
    const active = activeFilters(filters, {
        search: { label: t.common.search },
        status: { label: t.ops.status, value: (value) => optionLabel(t.labels, value) },
        customer_id: { label: t.ops.customer, value: (value) => customers.find((customer) => customer.id === value)?.name ?? value },
        from: { label: t.dashboard.from, value: (value) => format.date(value) },
        to: { label: t.dashboard.to, value: (value) => format.date(value) },
        overdue: { label: t.dashboard.overdue, value: () => '' },
    });

    const columns: Column<InvoiceRow>[] = [
        { key: 'number', header: t.finance.invoiceNumber, primary: true, cell: (row) => <bdi dir="ltr" className="md:whitespace-nowrap">{row.invoice_number}</bdi> },
        { key: 'customer', header: t.ops.customer, truncate: (row) => row.customer?.name, cell: (row) => row.customer?.name ?? t.common.none },
        { key: 'issued', header: t.finance.issueDate, cell: (row) => <span className="md:whitespace-nowrap tabular-nums">{format.date(row.issue_date)}</span> },
        {
            key: 'due',
            header: t.finance.dueDate,
            cell: (row) =>
                row.due_date ? (
                    <span className={`md:whitespace-nowrap tabular-nums ${row.status === 'overdue' ? 'font-medium text-danger' : ''}`}>{format.date(row.due_date)}</span>
                ) : (
                    <span className="text-ink-muted">{t.common.none}</span>
                ),
        },
        { key: 'total', header: t.finance.total, numeric: true, cell: (row) => format.money(row.total, row.currency) },
        {
            key: 'outstanding',
            header: t.finance.outstanding,
            numeric: true,
            cell: (row) => <span className={Number(row.amount_due) > 0 ? 'font-semibold text-ink' : 'text-ink-muted'}>{format.money(row.amount_due, row.currency)}</span>,
        },
        { key: 'status', header: t.ops.status, cell: (row) => <StatusBadge value={row.status} /> },
    ];

    return (
        <>
            <PageHeader
                title={t.nav.invoices}
                action={
                    canManage ? (
                        <ButtonLink variant="primary" href="/invoices/create">
                            {t.ops.create}
                        </ButtonLink>
                    ) : null
                }
            />
            <FilterBar search={<SearchInput defaultValue={filters.search} placeholder={t.common.search} />} active={active}>
                <select className={controlClassName} name="status" defaultValue={filters.status ?? ''} aria-label={t.ops.status}>
                    <option value="">{t.ops.status}</option>
                    {INVOICE_STATUSES.map((status) => (
                        <option key={status} value={status}>
                            {optionLabel(t.labels, status)}
                        </option>
                    ))}
                </select>
                <select className={controlClassName} name="customer_id" defaultValue={filters.customer_id ?? ''} aria-label={t.ops.customer}>
                    <option value="">{t.ops.customer}</option>
                    {customers.map((customer) => (
                        <option key={customer.id} value={customer.id}>
                            {customer.name}
                        </option>
                    ))}
                </select>
                <input className={controlClassName} type="date" name="from" defaultValue={filters.from ?? ''} aria-label={t.dashboard.from} />
                <input className={controlClassName} type="date" name="to" defaultValue={filters.to ?? ''} aria-label={t.dashboard.to} />
                <label className="flex min-h-10 items-center gap-2 text-body text-ink-secondary">
                    <input type="checkbox" name="overdue" value="1" defaultChecked={filters.overdue === '1'} />
                    {t.dashboard.overdue}
                </label>
            </FilterBar>
            <RecordList empty={invoices.data.length === 0} filtered={active.length > 0} loading="inline">
                <DataTable
                    caption={t.nav.invoices}
                    columns={columns}
                    rows={invoices.data}
                    rowKey={(row) => row.id}
                    actions={(row) => [{ key: 'view', label: t.common.view, href: `/invoices/${row.id}`, inline: true }]}
                />
                <Pagination paginator={invoices} />
            </RecordList>
        </>
    );
}

Index.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
