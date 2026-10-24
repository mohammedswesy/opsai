import { router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { Button } from '@/components/Button';
import { Chip } from '@/components/Chip';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { DescriptionItem, DescriptionList } from '@/components/DescriptionList';
import { MoneySummary } from '@/components/MoneySummary';
import { RecordHeader } from '@/components/RecordHeader';
import { Section } from '@/components/Section';
import { StatusBadge } from '@/components/StatusBadge';
import { TextLink } from '@/components/TextLink';
import { useI18n } from '@/i18n';
import AppLayout from '@/layouts/AppLayout';
import { useFormat } from '@/lib/format';
import { optionLabel } from '@/lib/labels';
import { useCan } from '@/lib/permissions';

type Payment = {
    id: string;
    amount: string;
    currency: string;
    method: string;
    status: string;
    reference: string | null;
    paid_at: string;
    notes: string | null;
    created_at: string | null;
    invoice: {
        id: string;
        invoice_number: string;
        status: string;
        currency: string;
        total: string;
        amount_paid: string;
        amount_due: string;
        customer: { id: string; name: string } | null;
    } | null;
    creator: { name: string } | null;
};

export default function Show({ payment, canRefund }: { payment: Payment; canRefund: boolean }) {
    const { t } = useI18n();
    const format = useFormat();
    const can = useCan();
    const [confirming, setConfirming] = useState(false);
    const invoice = payment.invoice;
    const customer = invoice?.customer ?? null;

    return (
        <>
            <RecordHeader
                eyebrow={t.detail.payment}
                title={format.money(payment.amount, payment.currency)}
                badges={
                    <>
                        <StatusBadge value={payment.status} />
                        <Chip>{optionLabel(t.labels, payment.method)}</Chip>
                    </>
                }
                meta={[
                    { key: 'paid', label: t.finance.paidAt, value: <span className="tabular-nums">{format.dateTime(payment.paid_at)}</span> },
                    { key: 'customer', label: t.ops.customer, value: customer ? <bdi>{customer.name}</bdi> : t.common.none },
                ]}
                actions={
                    canRefund && payment.status === 'completed' ? (
                        <Button variant="secondary" type="button" onClick={() => setConfirming(true)}>
                            {t.finance.refund}
                        </Button>
                    ) : null
                }
            />

            <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <Section title={t.detail.paymentDetails}>
                    <DescriptionList>
                        <DescriptionItem label={t.finance.amount}>
                            <span className="font-semibold tabular-nums">{format.money(payment.amount, payment.currency)}</span>
                        </DescriptionItem>
                        <DescriptionItem label={t.finance.method}>{optionLabel(t.labels, payment.method)}</DescriptionItem>
                        <DescriptionItem label={t.ops.status}>
                            <StatusBadge value={payment.status} />
                        </DescriptionItem>
                        <DescriptionItem label={t.finance.paidAt}>
                            <span className="tabular-nums">{format.dateTime(payment.paid_at)}</span>
                        </DescriptionItem>
                        <DescriptionItem label={t.finance.reference} ltr>
                            {payment.reference}
                        </DescriptionItem>
                        <DescriptionItem label={t.detail.recordedBy} isolate>
                            {payment.creator?.name}
                        </DescriptionItem>
                        <DescriptionItem label={t.detail.created}>{payment.created_at ? format.dateTime(payment.created_at) : null}</DescriptionItem>
                        <DescriptionItem label={t.common.notes} full isolate multiline>
                            {payment.notes}
                        </DescriptionItem>
                    </DescriptionList>
                </Section>

                <aside className="min-w-0 space-y-5">
                    <Section title={t.detail.invoice}>
                        {invoice ? (
                            <div className="space-y-4">
                                <DescriptionList columns={1}>
                                    <DescriptionItem label={t.finance.invoiceNumber}>
                                        <TextLink href={`/invoices/${invoice.id}`}>{invoice.invoice_number}</TextLink>
                                    </DescriptionItem>
                                    <DescriptionItem label={t.ops.customer}>
                                        {customer ? (
                                            can('customers.view') ? (
                                                <TextLink href={`/customers/${customer.id}`}>
                                                    <bdi>{customer.name}</bdi>
                                                </TextLink>
                                            ) : (
                                                <bdi>{customer.name}</bdi>
                                            )
                                        ) : null}
                                    </DescriptionItem>
                                    <DescriptionItem label={t.ops.status}>
                                        <StatusBadge value={invoice.status} />
                                    </DescriptionItem>
                                </DescriptionList>
                                <MoneySummary
                                    rows={[
                                        { key: 'total', label: t.finance.total, value: format.money(invoice.total, invoice.currency) },
                                        { key: 'paid', label: t.finance.paidAmount, value: format.money(invoice.amount_paid, invoice.currency), tone: 'success' },
                                        { key: 'due', label: t.finance.outstanding, value: format.money(invoice.amount_due, invoice.currency), emphasis: 'total' },
                                    ]}
                                />
                            </div>
                        ) : (
                            <p className="text-body text-ink-muted">{t.common.none}</p>
                        )}
                    </Section>
                </aside>
            </div>

            <ConfirmDialog
                open={confirming}
                title={t.finance.confirmRefund}
                confirmLabel={t.common.confirm}
                cancelLabel={t.common.cancel}
                onCancel={() => setConfirming(false)}
                onConfirm={() => router.post(`/payments/${payment.id}/refund`)}
            />
        </>
    );
}

Show.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
