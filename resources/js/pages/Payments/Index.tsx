import { Button, ButtonLink } from '@/components/Button';
import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { DataTable } from '@/components/DataTable';
import { EmptyState } from '@/components/EmptyState';
import { FilterBar } from '@/components/FilterBar';
import { PageHeader } from '@/components/PageHeader';
import { Pagination } from '@/components/Pagination';
import { SearchInput } from '@/components/SearchInput';
import { StatusBadge } from '@/components/StatusBadge';
import { useI18n } from '@/i18n';
import AppLayout from '@/layouts/AppLayout';
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
            <FilterBar>
                <SearchInput defaultValue={filters.search} placeholder={t.common.search} />
                <Button variant="primary" type="submit">
                    {t.common.search}
                </Button>
            </FilterBar>
            {payments.data.length === 0 ? (
                <EmptyState title={t.ops.empty} />
            ) : (
                <DataTable headers={[t.finance.invoiceNumber, t.finance.amount, t.finance.method, t.ops.status, t.finance.reference, '']}>
                    {payments.data.map((payment) => (
                        <tr key={payment.id} className="border-b border-zinc-100">
                            <td className="px-4 py-3">{payment.invoice?.invoice_number}</td>
                            <td className="px-4 py-3">
                                {payment.amount} {payment.currency}
                            </td>
                            <td className="px-4 py-3">
                                <StatusBadge value={payment.method} />
                            </td>
                            <td className="px-4 py-3">
                                <StatusBadge value={payment.status} />
                            </td>
                            <td className="px-4 py-3">{payment.reference ?? t.common.none}</td>
                            <td className="px-4 py-3 text-end">
                                <Link href={`/payments/${payment.id}`} className="text-teal-800">
                                    {t.common.view}
                                </Link>
                            </td>
                        </tr>
                    ))}
                </DataTable>
            )}
            <Pagination links={payments.links} />
        </>
    );
}

Index.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
