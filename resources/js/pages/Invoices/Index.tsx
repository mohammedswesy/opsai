import { Button, ButtonLink } from '@/components/Button';
import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { DataTable } from '@/components/DataTable';
import { EmptyState } from '@/components/EmptyState';
import { FilterBar } from '@/components/FilterBar';
import { controlClassName } from '@/components/FormField';
import { PageHeader } from '@/components/PageHeader';
import { Pagination } from '@/components/Pagination';
import { SearchInput } from '@/components/SearchInput';
import { StatusBadge } from '@/components/StatusBadge';
import { useI18n } from '@/i18n';
import { optionLabel } from '@/lib/labels';
import AppLayout from '@/layouts/AppLayout';
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

export default function Index({
    invoices,
    filters,
    customers,
    canManage,
}: {
    invoices: Paginator<InvoiceRow>;
    filters: { search?: string; status?: string; customer_id?: string; from?: string; to?: string; overdue?: string };
    customers: Option[];
    canManage: boolean;
}) {
    const { t } = useI18n();

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
            <FilterBar>
                <SearchInput defaultValue={filters.search} placeholder={t.common.search} />
                <select className={controlClassName} name="status" defaultValue={filters.status ?? ''}>
                    <option value="">{t.ops.status}</option>
                    {['draft', 'issued', 'partially_paid', 'paid', 'overdue', 'cancelled'].map((status) => (
                        <option key={status} value={status}>
                            {optionLabel(t.labels, status)}
                        </option>
                    ))}
                </select>
                <select className={controlClassName} name="customer_id" defaultValue={filters.customer_id ?? ''}>
                    <option value="">{t.ops.customer}</option>
                    {customers.map((customer) => (
                        <option key={customer.id} value={customer.id}>
                            {customer.name}
                        </option>
                    ))}
                </select>
                <input className={controlClassName} type="date" name="from" defaultValue={filters.from ?? ''} aria-label={t.dashboard.from} />
                <input className={controlClassName} type="date" name="to" defaultValue={filters.to ?? ''} aria-label={t.dashboard.to} />
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="overdue" value="1" defaultChecked={filters.overdue === '1'} />
                    {t.dashboard.overdue}
                </label>
                <Button variant="primary" type="submit">
                    {t.common.search}
                </Button>
            </FilterBar>
            {invoices.data.length === 0 ? (
                <EmptyState title={t.ops.empty} />
            ) : (
                <DataTable headers={[t.finance.invoiceNumber, t.ops.customer, t.finance.issueDate, t.finance.total, t.finance.outstanding, t.ops.status, '']}>
                    {invoices.data.map((invoice) => (
                        <tr key={invoice.id} className="border-b border-zinc-100">
                            <td className="px-4 py-3 font-medium">{invoice.invoice_number}</td>
                            <td className="max-w-48 truncate px-4 py-3" title={invoice.customer?.name}>{invoice.customer?.name}</td>
                            <td className="px-4 py-3">{invoice.issue_date.slice(0, 10)}</td>
                            <td className="px-4 py-3">
                                {invoice.total} {invoice.currency}
                            </td>
                            <td className={`px-4 py-3 font-semibold tabular-nums ${Number(invoice.amount_due) > 0 ? 'text-zinc-900' : 'text-zinc-500'}`}>{invoice.amount_due} {invoice.currency}</td>
                            <td className="px-4 py-3">
                                <StatusBadge value={invoice.status} />
                            </td>
                            <td className="px-4 py-3 text-end">
                                <Link href={`/invoices/${invoice.id}`} className="text-teal-800">
                                    {t.common.view}
                                </Link>
                            </td>
                        </tr>
                    ))}
                </DataTable>
            )}
            <Pagination links={invoices.links} />
        </>
    );
}

Index.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
