import { router, usePage } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { ActivityTimeline, type ActivityEntry } from '@/components/ActivityTimeline';
import { Button, ButtonLink } from '@/components/Button';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { DescriptionItem, DescriptionList } from '@/components/DescriptionList';
import { MoneySummary } from '@/components/MoneySummary';
import { RecordHeader } from '@/components/RecordHeader';
import { Section } from '@/components/Section';
import { StatusBadge } from '@/components/StatusBadge';
import { ExternalTextLink, TextLink } from '@/components/TextLink';
import { useI18n } from '@/i18n';
import AppLayout from '@/layouts/AppLayout';
import { useFormat } from '@/lib/format';
import { optionLabel } from '@/lib/labels';
import { useCan } from '@/lib/permissions';

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
    branch: { name: string } | null;
    customer: { id: string; name: string; phone: string | null; email: string | null; address: string | null; city: string | null; country: string | null } | null;
    work_order: { id: string; title: string } | null;
    items: { id: string; description: string; quantity: string; unit_price: string; tax: string; total: string; service: { name: string } | null }[];
    payments: { id: string; amount: string; method: string; status: string; paid_at: string; reference: string | null }[];
    activities: ActivityEntry[];
};

type Letterhead = { name: string; tax_number: string | null };

export default function Show({ invoice, canManage, canPay, letterhead = null }: { invoice: Invoice; canManage: boolean; canPay: boolean; letterhead?: Letterhead | null }) {
    const { t } = useI18n();
    const format = useFormat();
    const can = useCan();
    const { organization } = usePage().props;
    const [canceling, setCanceling] = useState(false);
    const payable = ['issued', 'partially_paid', 'overdue'].includes(invoice.status);
    const money = (value: string) => format.money(value, invoice.currency);
    const customer = invoice.customer;
    const separator = format.locale === 'ar' ? '، ' : ', ';
    const address = customer ? [customer.address, customer.city, customer.country].filter(Boolean).join(separator) : '';
    const hasDiscount = Number(invoice.discount) > 0;

    return (
        <>
            <RecordHeader parent={{ label: t.nav.invoices, href: '/invoices' }}
                eyebrow={t.detail.invoice}
                title={invoice.invoice_number}
                badges={<StatusBadge value={invoice.status} />}
                meta={[
                    { key: 'issue', label: t.finance.issueDate, value: <span className="tabular-nums">{format.date(invoice.issue_date)}</span> },
                    { key: 'due', label: t.finance.dueDate, value: invoice.due_date ? <span className="tabular-nums">{format.date(invoice.due_date)}</span> : t.common.none },
                    { key: 'outstanding', label: t.finance.outstanding, value: <span className="font-medium tabular-nums">{money(invoice.amount_due)}</span> },
                ]}
                actions={
                    <>
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
                    </>
                }
            />

            <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_20rem] print:block">
                <article className="min-w-0 rounded-card border border-line bg-surface shadow-card print:border-0 print:shadow-none">
                    <div className="grid gap-6 p-5 sm:grid-cols-2">
                        <div className="min-w-0">
                            <h2 className="text-caption font-semibold text-ink-muted">{t.detail.from}</h2>
                            <p className="mt-1 text-body font-medium text-ink">
                                <bdi>{letterhead?.name ?? organization?.name}</bdi>
                            </p>
                            {letterhead?.tax_number ? (
                                <p className="text-caption text-ink-secondary" data-testid="invoice-tax-number">
                                    {t.organizations.taxNumber}: <bdi dir="ltr" className="tabular-nums">{letterhead.tax_number}</bdi>
                                </p>
                            ) : null}
                            {invoice.branch ? (
                                <p className="text-caption text-ink-secondary">
                                    <bdi>{invoice.branch.name}</bdi>
                                </p>
                            ) : null}
                        </div>
                        <div className="min-w-0">
                            <h2 className="text-caption font-semibold text-ink-muted">{t.detail.billTo}</h2>
                            {customer ? (
                                <div className="mt-1 space-y-0.5 text-body">
                                    <p className="font-medium">
                                        {can('customers.view') ? (
                                            <TextLink href={`/customers/${customer.id}`}>
                                                <bdi>{customer.name}</bdi>
                                            </TextLink>
                                        ) : (
                                            <bdi className="text-ink">{customer.name}</bdi>
                                        )}
                                    </p>
                                    {address ? (
                                        <p className="text-caption text-ink-secondary">
                                            <bdi>{address}</bdi>
                                        </p>
                                    ) : null}
                                    {customer.phone ? (
                                        <p className="text-caption text-ink-secondary">
                                            <bdi dir="ltr">{customer.phone}</bdi>
                                        </p>
                                    ) : null}
                                    {customer.email ? (
                                        <p className="text-caption">
                                            <ExternalTextLink href={`mailto:${customer.email}`}>
                                                <bdi dir="ltr">{customer.email}</bdi>
                                            </ExternalTextLink>
                                        </p>
                                    ) : null}
                                </div>
                            ) : (
                                <p className="mt-1 text-body text-ink-subtle">{t.common.none}</p>
                            )}
                        </div>
                    </div>

                    <div className="border-t border-line p-5">
                        <DescriptionList columns={3}>
                            <DescriptionItem label={t.finance.issueDate}>
                                <span className="tabular-nums">{format.date(invoice.issue_date)}</span>
                            </DescriptionItem>
                            <DescriptionItem label={t.finance.dueDate}>{invoice.due_date ? <span className="tabular-nums">{format.date(invoice.due_date)}</span> : null}</DescriptionItem>
                            <DescriptionItem label={t.finance.currency}>{invoice.currency}</DescriptionItem>
                            <DescriptionItem label={t.ops.relatedWorkOrder} full>
                                {invoice.work_order ? (
                                    <TextLink href={`/work-orders/${invoice.work_order.id}`}>
                                        <bdi>{invoice.work_order.title}</bdi>
                                    </TextLink>
                                ) : null}
                            </DescriptionItem>
                        </DescriptionList>
                    </div>

                    <div className="overflow-x-auto border-t border-line">
                        <table className="min-w-full text-body">
                            <caption className="sr-only">{t.finance.items}</caption>
                            <thead>
                                <tr className="border-b border-line text-caption text-ink-muted">
                                    <th scope="col" className="px-5 py-2.5 text-start font-medium">
                                        {t.ops.description}
                                    </th>
                                    <th scope="col" className="px-3 py-2.5 text-end font-medium">
                                        {t.finance.quantity}
                                    </th>
                                    <th scope="col" className="px-3 py-2.5 text-end font-medium">
                                        {t.finance.unitPrice}
                                    </th>
                                    <th scope="col" className="px-3 py-2.5 text-end font-medium">
                                        {t.detail.lineTax}
                                    </th>
                                    <th scope="col" className="px-5 py-2.5 text-end font-medium">
                                        {t.finance.total}
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="text-ink">
                                {invoice.items.map((item) => (
                                    <tr key={item.id}>
                                        <td className="min-w-48 px-5 py-3 align-top">
                                            <bdi className="break-words">{item.description}</bdi>
                                            {item.service ? (
                                                <p className="text-caption text-ink-muted">
                                                    <bdi>{item.service.name}</bdi>
                                                </p>
                                            ) : null}
                                        </td>
                                        <td className="px-3 py-3 text-end align-top whitespace-nowrap tabular-nums">{format.number(item.quantity, { maximumFractionDigits: 2 })}</td>
                                        <td className="px-3 py-3 text-end align-top whitespace-nowrap tabular-nums">{money(item.unit_price)}</td>
                                        <td className="px-3 py-3 text-end align-top whitespace-nowrap tabular-nums">{money(item.tax)}</td>
                                        <td className="px-5 py-3 text-end align-top font-medium whitespace-nowrap tabular-nums">{money(item.total)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div className="flex flex-col gap-6 border-t border-line p-5 sm:flex-row sm:items-start sm:justify-between">
                        <div className="min-w-0 sm:max-w-sm">
                            {invoice.notes ? (
                                <>
                                    <h2 className="text-caption font-semibold text-ink-muted">{t.common.notes}</h2>
                                    <p className="mt-1 text-body whitespace-pre-wrap break-words text-ink">
                                        <bdi>{invoice.notes}</bdi>
                                    </p>
                                </>
                            ) : null}
                        </div>
                        <MoneySummary
                            className="w-full sm:ms-auto sm:max-w-xs"
                            rows={[
                                { key: 'subtotal', label: t.finance.subtotal, value: money(invoice.subtotal) },
                                ...(hasDiscount ? [{ key: 'discount', label: t.finance.discount, value: money(invoice.discount) }] : []),
                                { key: 'tax', label: t.finance.tax, value: money(invoice.tax) },
                                { key: 'total', label: t.finance.total, value: money(invoice.total), emphasis: 'strong' as const },
                                { key: 'paid', label: t.finance.paidAmount, value: money(invoice.amount_paid), tone: 'success' as const },
                                { key: 'due', label: t.finance.outstanding, value: money(invoice.amount_due), emphasis: 'total' as const },
                            ]}
                        />
                    </div>
                </article>

                <aside className="min-w-0 space-y-5 print:hidden">
                    <Section title={t.nav.payments}>
                        {invoice.payments.length === 0 ? (
                            <p className="text-body text-ink-muted">{t.common.none}</p>
                        ) : (
                            <ul className="divide-y divide-line">
                                {invoice.payments.map((payment) => (
                                    <li key={payment.id} className="flex items-start justify-between gap-3 py-2.5 first:pt-0 last:pb-0">
                                        <div className="min-w-0 text-body">
                                            <TextLink href={`/payments/${payment.id}`} className="tabular-nums">
                                                {money(payment.amount)}
                                            </TextLink>
                                            <p className="mt-0.5 text-caption text-ink-muted tabular-nums">
                                                {format.dateTime(payment.paid_at)} · {optionLabel(t.labels, payment.method)}
                                            </p>
                                        </div>
                                        <StatusBadge value={payment.status} />
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Section>

                    <Section title={t.ops.activity}>
                        <ActivityTimeline items={invoice.activities} />
                    </Section>
                </aside>
            </div>

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
