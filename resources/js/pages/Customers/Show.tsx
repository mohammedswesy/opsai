import type { ReactNode } from 'react';
import { ActivityTimeline, type ActivityEntry } from '@/components/ActivityTimeline';
import { ButtonLink } from '@/components/Button';
import { DescriptionItem, DescriptionList } from '@/components/DescriptionList';
import { MoneySummary } from '@/components/MoneySummary';
import { PriorityBadge } from '@/components/PriorityBadge';
import { RecordHeader, type RecordMeta } from '@/components/RecordHeader';
import { Section } from '@/components/Section';
import { StatusBadge } from '@/components/StatusBadge';
import { ExternalTextLink, TextLink } from '@/components/TextLink';
import { useI18n } from '@/i18n';
import AppLayout from '@/layouts/AppLayout';
import { useFormat } from '@/lib/format';
import { optionLabel } from '@/lib/labels';

type RequestRow = { id: string; subject: string; status: string; priority: string; requested_at: string | null };
type OrderRow = { id: string; title: string; status: string; priority: string; created_at: string | null };
type AppointmentRow = { id: string; scheduled_start: string; scheduled_end: string; status: string };

type Finance = {
    currency: string;
    invoiced: string;
    paid: string;
    outstanding: string;
    overdue: string;
    invoices: { id: string; invoice_number: string; status: string; total: string; amount_due: string; currency: string; issue_date: string }[];
    payments: { id: string; amount: string; currency: string; status: string; paid_at: string; invoice: { invoice_number: string } | null }[];
};

type Customer = {
    id: string;
    name: string;
    type: string;
    company_name: string | null;
    email: string | null;
    phone: string;
    whatsapp_number: string | null;
    address: string | null;
    city: string | null;
    country: string | null;
    notes: string | null;
    source: string | null;
    status: string;
    created_at: string | null;
    branch: { name: string } | null;
};

export default function Show({
    customer,
    requests,
    workOrders,
    appointments,
    activities,
    finance,
    canManage,
}: {
    customer: Customer;
    requests: RequestRow[];
    workOrders: OrderRow[];
    appointments: AppointmentRow[];
    activities: ActivityEntry[];
    finance: Finance | null;
    canManage: boolean;
}) {
    const { t } = useI18n();
    const format = useFormat();
    const separator = format.locale === 'ar' ? '، ' : ', ';
    const address = [customer.address, customer.city, customer.country].filter(Boolean).join(separator);
    const typeLabel = customer.type === 'company' ? t.labels.company : t.labels.individual;
    const meta: RecordMeta[] = [
        { key: 'phone', label: t.ops.phone, value: <bdi dir="ltr">{customer.phone}</bdi> },
        { key: 'branch', label: t.ops.branch, value: customer.branch ? <bdi>{customer.branch.name}</bdi> : t.common.none },
    ];

    if (finance) {
        meta.push({ key: 'outstanding', label: t.finance.outstanding, value: <span className="font-medium tabular-nums">{format.money(finance.outstanding, finance.currency)}</span> });
    }

    return (
        <>
            <RecordHeader parent={{ label: t.nav.customers, href: '/customers' }}
                eyebrow={typeLabel}
                title={customer.name}
                badges={<StatusBadge value={customer.status} />}
                meta={meta}
                actions={
                    canManage ? (
                        <ButtonLink variant="primary" href={`/customers/${customer.id}/edit`}>
                            {t.common.edit}
                        </ButtonLink>
                    ) : null
                }
            />

            <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <div className="min-w-0 space-y-5">
                    <Section title={t.detail.contact}>
                        <DescriptionList>
                            <DescriptionItem label={t.ops.phone} ltr>
                                <ExternalTextLink href={`tel:${customer.phone}`}>{customer.phone}</ExternalTextLink>
                            </DescriptionItem>
                            <DescriptionItem label={t.ops.whatsapp} ltr>
                                {customer.whatsapp_number}
                            </DescriptionItem>
                            <DescriptionItem label={t.detail.email} ltr>
                                {customer.email ? <ExternalTextLink href={`mailto:${customer.email}`}>{customer.email}</ExternalTextLink> : null}
                            </DescriptionItem>
                            <DescriptionItem label={t.ops.company} isolate>
                                {customer.company_name}
                            </DescriptionItem>
                            <DescriptionItem label={t.ops.address} full isolate>
                                {address}
                            </DescriptionItem>
                            <DescriptionItem label={t.ops.type}>{typeLabel}</DescriptionItem>
                            <DescriptionItem label={t.ops.source}>{customer.source ? optionLabel(t.labels, customer.source) : null}</DescriptionItem>
                            <DescriptionItem label={t.ops.branch} isolate>
                                {customer.branch?.name}
                            </DescriptionItem>
                            <DescriptionItem label={t.detail.customerSince}>{customer.created_at ? format.date(new Date(customer.created_at)) : null}</DescriptionItem>
                            <DescriptionItem label={t.common.notes} full isolate multiline>
                                {customer.notes}
                            </DescriptionItem>
                        </DescriptionList>
                    </Section>

                    <Section title={t.nav.requests}>
                        <RowList
                            empty={t.ops.empty}
                            rows={requests.map((row) => ({
                                id: row.id,
                                main: (
                                    <TextLink href={`/requests/${row.id}`}>
                                        <bdi>{row.subject}</bdi>
                                    </TextLink>
                                ),
                                sub: row.requested_at ? format.dateTime(row.requested_at) : null,
                                end: (
                                    <>
                                        <PriorityBadge value={row.priority} />
                                        <StatusBadge value={row.status} />
                                    </>
                                ),
                            }))}
                        />
                    </Section>

                    <Section title={t.nav.workOrders}>
                        <RowList
                            empty={t.ops.empty}
                            rows={workOrders.map((row) => ({
                                id: row.id,
                                main: (
                                    <TextLink href={`/work-orders/${row.id}`}>
                                        <bdi>{row.title}</bdi>
                                    </TextLink>
                                ),
                                sub: row.created_at ? format.dateTime(row.created_at) : null,
                                end: (
                                    <>
                                        <PriorityBadge value={row.priority} />
                                        <StatusBadge value={row.status} />
                                    </>
                                ),
                            }))}
                        />
                    </Section>

                    <Section title={t.nav.appointments}>
                        <RowList
                            empty={t.ops.empty}
                            rows={appointments.map((row) => ({
                                id: row.id,
                                main: (
                                    <span className="text-ink tabular-nums">
                                        {format.dateTime(row.scheduled_start)} – {format.sameDay(row.scheduled_start, row.scheduled_end) ? format.time(row.scheduled_end) : format.dateTime(row.scheduled_end)}
                                    </span>
                                ),
                                end: <StatusBadge value={row.status} />,
                            }))}
                        />
                    </Section>

                    <Section title={t.ops.activity}>
                        <ActivityTimeline items={activities} />
                    </Section>
                </div>

                <aside className="min-w-0 space-y-5">
                    {finance ? (
                        <Section title={t.finance.balance} data-testid="customer-balance">
                            <MoneySummary
                                rows={[
                                    { key: 'invoiced', label: t.finance.invoiced, value: format.money(finance.invoiced, finance.currency) },
                                    { key: 'paid', label: t.finance.paidAmount, value: format.money(finance.paid, finance.currency), tone: 'success' },
                                    {
                                        key: 'overdue',
                                        label: t.finance.overdue,
                                        value: format.money(finance.overdue, finance.currency),
                                        tone: Number(finance.overdue) > 0 ? 'danger' : undefined,
                                    },
                                    { key: 'outstanding', label: t.finance.outstanding, value: format.money(finance.outstanding, finance.currency), emphasis: 'total' },
                                ]}
                            />

                            <h3 className="mt-5 mb-2 text-caption font-semibold text-ink-secondary">{t.finance.recentInvoices}</h3>
                            <RowList
                                empty={t.common.none}
                                rows={finance.invoices.map((invoice) => ({
                                    id: invoice.id,
                                    main: <TextLink href={`/invoices/${invoice.id}`}>{invoice.invoice_number}</TextLink>,
                                    sub: `${format.date(invoice.issue_date)} · ${format.money(invoice.total, invoice.currency)}`,
                                    end: <StatusBadge value={invoice.status} />,
                                }))}
                            />

                            <h3 className="mt-5 mb-2 text-caption font-semibold text-ink-secondary">{t.finance.recentPayments}</h3>
                            <RowList
                                empty={t.common.none}
                                rows={finance.payments.map((payment) => ({
                                    id: payment.id,
                                    main: <TextLink href={`/payments/${payment.id}`}>{format.money(payment.amount, payment.currency ?? finance.currency)}</TextLink>,
                                    sub: [payment.invoice?.invoice_number, payment.paid_at ? format.date(new Date(payment.paid_at)) : null].filter(Boolean).join(' · '),
                                    end: <StatusBadge value={payment.status} />,
                                }))}
                            />
                        </Section>
                    ) : null}
                </aside>
            </div>
        </>
    );
}

function RowList({ rows, empty }: { rows: { id: string; main: ReactNode; sub?: ReactNode; end?: ReactNode }[]; empty: string }) {
    if (rows.length === 0) {
        return <p className="text-body text-ink-muted">{empty}</p>;
    }

    return (
        <ul className="divide-y divide-line">
            {rows.map((row) => (
                <li key={row.id} className="flex items-start justify-between gap-3 py-2.5 first:pt-0 last:pb-0">
                    <div className="min-w-0 text-body break-words">
                        {row.main}
                        {row.sub ? <p className="mt-0.5 text-caption text-ink-muted tabular-nums">{row.sub}</p> : null}
                    </div>
                    {row.end ? <div className="flex shrink-0 flex-wrap items-center justify-end gap-1.5">{row.end}</div> : null}
                </li>
            ))}
        </ul>
    );
}

Show.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
