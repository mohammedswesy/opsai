import { Card } from '@/components/Card';
import { Button, ButtonLink } from '@/components/Button';
import { Link, router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { PageHeader } from '@/components/PageHeader';
import { StatusBadge } from '@/components/StatusBadge';
import { useI18n } from '@/i18n';
import AppLayout from '@/layouts/AppLayout';

type Invoice = {
    id: string;
    invoice_number: string;
    status: string;
    issue_date: string;
    due_date: string | null;
    currency: string;
    subtotal: string;
    discount: string;
    tax: string;
    total: string;
    amount_paid: string;
    amount_due: string;
    notes: string | null;
    customer: { id: string; name: string } | null;
    work_order: { id: string; title: string } | null;
    items: { id: string; description: string; quantity: string; unit_price: string; tax: string; total: string }[];
    payments: { id: string; amount: string; method: string; status: string; paid_at: string; reference: string | null }[];
    activities: { id: string; action: string; description: string | null; actor: { name: string } | null }[];
};

export default function Show({ invoice, canManage, canPay }: { invoice: Invoice; canManage: boolean; canPay: boolean }) {
    const { t } = useI18n();
    const [canceling, setCanceling] = useState(false);
    const payable = ['issued', 'partially_paid', 'overdue'].includes(invoice.status);

    return (
        <>
            <div className="print:hidden">
            <PageHeader
                title={invoice.invoice_number}
                action={
                    <div className="flex flex-wrap gap-2">
                        {canManage && invoice.status === 'draft' ? (
                            <ButtonLink variant="secondary" href={`/invoices/${invoice.id}/edit`}>
                                {t.common.edit}
                            </ButtonLink>
                        ) : null}
                        {canManage && invoice.status === 'draft' ? (
                            <Button variant="primary" type="button" onClick={() => router.post(`/invoices/${invoice.id}/issue`)}>
                                {t.finance.issue}
                            </Button>
                        ) : null}
                        {canPay && payable ? (
                            <ButtonLink variant="primary" href={`/payments/create?invoice_id=${invoice.id}`}>
                                {t.finance.recordPayment}
                            </ButtonLink>
                        ) : null}
                        {canManage && ['draft', 'issued', 'overdue'].includes(invoice.status) && Number(invoice.amount_paid) === 0 ? (
                            <Button variant="secondary" type="button" onClick={() => setCanceling(true)}>
                                {t.finance.cancelInvoice}
                            </Button>
                        ) : null}
                    </div>
                }
            />
            </div>
            <article className="print:text-black">
            <h1 className="mb-4 hidden text-xl font-semibold print:block">{invoice.invoice_number}</h1>
            <Card className="mb-6 grid gap-3 p-5 shadow-sm print:border-0 print:shadow-none sm:grid-cols-2">
                <p>
                    {t.ops.customer}: {invoice.customer ? <Link href={`/customers/${invoice.customer.id}`}>{invoice.customer.name}</Link> : t.common.none}
                </p>
                <p>
                    {t.ops.relatedWorkOrder}: {invoice.work_order ? <Link href={`/work-orders/${invoice.work_order.id}`}>{invoice.work_order.title}</Link> : t.common.none}
                </p>
                <p>
                    {t.finance.issueDate}: {invoice.issue_date.slice(0, 10)}
                </p>
                <p>
                    {t.finance.dueDate}: {invoice.due_date ? invoice.due_date.slice(0, 10) : t.common.none}
                </p>
                <p>
                    {t.finance.currency}: {invoice.currency}
                </p>
                <StatusBadge value={invoice.status} />
                <p>
                    {t.finance.subtotal}: {invoice.subtotal}
                </p>
                <p>
                    {t.finance.discount}: {invoice.discount}
                </p>
                <p>
                    {t.finance.tax}: {invoice.tax}
                </p>
                <p className="text-base font-semibold tabular-nums text-zinc-900">
                    {t.finance.total}: {invoice.total}
                </p>
                <p className="tabular-nums text-green-700">
                    {t.finance.paidAmount}: {invoice.amount_paid}
                </p>
                <p className="text-lg font-semibold tabular-nums text-zinc-900">
                    {t.finance.outstanding}: {invoice.amount_due} {invoice.currency}
                </p>
                {invoice.notes ? <p className="break-words sm:col-span-2">{invoice.notes}</p> : null}
            </Card>
            <Card className="mb-6 overflow-x-auto p-5">
                <h2 className="mb-3 font-medium">{t.finance.items}</h2>
                <table className="min-w-full text-sm">
                    <thead>
                        <tr className="text-zinc-500">
                            <th className="px-2 py-2 text-start">{t.ops.description}</th>
                            <th className="px-2 py-2 text-start">{t.finance.quantity}</th>
                            <th className="px-2 py-2 text-start">{t.finance.unitPrice}</th>
                            <th className="px-2 py-2 text-start">{t.finance.total}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {invoice.items.map((item) => (
                            <tr key={item.id}>
                                <td className="px-2 py-2">{item.description}</td>
                                <td className="px-2 py-2">{item.quantity}</td>
                                <td className="px-2 py-2">{item.unit_price}</td>
                                <td className="px-2 py-2">{item.total}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </Card>
            <Card className="mb-6 p-5 text-sm">
                <h2 className="mb-3 font-medium">{t.nav.payments}</h2>
                {invoice.payments.length === 0 ? (
                    <p className="text-zinc-500">{t.common.none}</p>
                ) : (
                    <ul className="space-y-2">
                        {invoice.payments.map((payment) => (
                            <li key={payment.id}>
                                <Link href={`/payments/${payment.id}`} className="text-teal-800">
                                    {payment.amount} {invoice.currency}
                                </Link>{' '}
                                <StatusBadge value={payment.status} />
                            </li>
                        ))}
                    </ul>
                )}
            </Card>
            <Card className="p-5 text-sm print:hidden">
                <h2 className="mb-3 font-medium">{t.ops.activity}</h2>
                {invoice.activities.length === 0 ? (
                    <p className="text-zinc-500">{t.ops.noActivity}</p>
                ) : (
                    <ul className="space-y-2">
                        {invoice.activities.map((activity) => (
                            <li key={activity.id}>
                                {activity.actor?.name ?? t.common.none} {activity.action} {activity.description}
                            </li>
                        ))}
                    </ul>
                )}
            </Card>
            </article>
            <ConfirmDialog
                open={canceling}
                title={t.finance.confirmCancel}
                confirmLabel={t.common.confirm}
                cancelLabel={t.common.cancel}
                onCancel={() => setCanceling(false)}
                onConfirm={() => router.post(`/invoices/${invoice.id}/cancel`)}
            />
        </>
    );
}

Show.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
