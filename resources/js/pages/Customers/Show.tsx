import { Card } from '@/components/Card';
import { Button, ButtonLink } from '@/components/Button';
import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { PageHeader } from '@/components/PageHeader';
import { PriorityBadge } from '@/components/PriorityBadge';
import { StatusBadge } from '@/components/StatusBadge';
import { useI18n } from '@/i18n';
import { optionLabel } from '@/lib/labels';
import AppLayout from '@/layouts/AppLayout';

type Activity = { id: string; action: string; description: string | null; created_at: string; actor: { name: string } | null };
type RequestRow = { id: string; subject: string; status: string; priority: string };
type OrderRow = { id: string; title: string; status: string };
type AppointmentRow = { id: string; scheduled_start: string; status: string };

export default function Show({
    customer,
    requests,
    workOrders,
    appointments,
    activities,
    finance,
    canManage,
}: {
    customer: {
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
        status: string;
        branch: { name: string } | null;
    };
    requests: RequestRow[];
    workOrders: OrderRow[];
    appointments: AppointmentRow[];
    activities: Activity[];
    finance: {
        currency: string;
        invoiced: string;
        paid: string;
        outstanding: string;
        overdue: string;
        invoices: { id: string; invoice_number: string; status: string; total: string; amount_due: string }[];
        payments: { id: string; amount: string; status: string; invoice: { invoice_number: string } | null }[];
    } | null;
    canManage: boolean;
}) {
    const { t } = useI18n();

    return (
        <>
            <PageHeader
                title={customer.name}
                subtitle={customer.type === 'company' ? t.labels.company : t.labels.individual}
                action={
                    canManage ? (
                        <ButtonLink variant="primary" href={`/customers/${customer.id}/edit`}>
                            {t.common.edit}
                        </ButtonLink>
                    ) : null
                }
            />
            <Card className="mb-6 grid gap-3 p-5 sm:grid-cols-2">
                <p>{customer.phone}</p>
                <p>{customer.email ?? t.common.none}</p>
                <p>{customer.whatsapp_number ?? t.common.none}</p>
                <p>{[customer.address, customer.city, customer.country].filter(Boolean).join(', ') || t.common.none}</p>
                <p>{customer.branch?.name ?? t.common.none}</p>
                <StatusBadge value={customer.status} />
                {customer.notes ? <p className="sm:col-span-2">{customer.notes}</p> : null}
            </Card>
            <Related title={t.nav.requests} rows={requests.map((row) => ({ id: row.id, href: `/requests/${row.id}`, label: row.subject, extra: <PriorityBadge value={row.priority} /> }))} empty={t.ops.empty} />
            <Related title={t.nav.workOrders} rows={workOrders.map((row) => ({ id: row.id, href: `/work-orders/${row.id}`, label: row.title, extra: <StatusBadge value={row.status} /> }))} empty={t.ops.empty} />
            <Related title={t.nav.appointments} rows={appointments.map((row) => ({ id: row.id, href: '/appointments', label: row.scheduled_start, extra: <StatusBadge value={row.status} /> }))} empty={t.ops.empty} />
            {finance ? (
                <Card className="mb-6 p-5 text-sm" data-testid="customer-balance">
                    <h2 className="mb-3 font-medium">{t.finance.balance}</h2>
                    <div className="mb-4 grid gap-2 sm:grid-cols-2">
                        <p>
                            {t.finance.invoiced}: {finance.invoiced} {finance.currency}
                        </p>
                        <p>
                            {t.finance.paidAmount}: {finance.paid} {finance.currency}
                        </p>
                        <p>
                            {t.finance.outstanding}: {finance.outstanding} {finance.currency}
                        </p>
                        <p>
                            {t.finance.overdue}: {finance.overdue} {finance.currency}
                        </p>
                    </div>
                    <h3 className="mb-2 font-medium">{t.finance.recentInvoices}</h3>
                    {finance.invoices.length === 0 ? (
                        <p className="mb-3 text-zinc-500">{t.common.none}</p>
                    ) : (
                        <ul className="mb-3 space-y-1">
                            {finance.invoices.map((invoice) => (
                                <li key={invoice.id}>
                                    <Link href={`/invoices/${invoice.id}`} className="text-teal-800">
                                        {invoice.invoice_number}
                                    </Link>{' '}
                                    {invoice.total} <StatusBadge value={invoice.status} />
                                </li>
                            ))}
                        </ul>
                    )}
                    <h3 className="mb-2 font-medium">{t.finance.recentPayments}</h3>
                    {finance.payments.length === 0 ? (
                        <p className="text-zinc-500">{t.common.none}</p>
                    ) : (
                        <ul className="space-y-1">
                            {finance.payments.map((payment) => (
                                <li key={payment.id}>
                                    <Link href={`/payments/${payment.id}`} className="text-teal-800">
                                        {payment.amount} {finance.currency}
                                    </Link>{' '}
                                    {payment.invoice?.invoice_number} <StatusBadge value={payment.status} />
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
            ) : null}
            <Card className="p-5">
                <h2 className="mb-3 font-medium">{t.ops.activity}</h2>
                {activities.length === 0 ? (
                    <p className="text-sm text-zinc-500">{t.ops.noActivity}</p>
                ) : (
                    <ul className="space-y-2 text-sm">
                        {activities.map((activity) => (
                            <li key={activity.id}>
                                <span className="font-medium">{activity.actor?.name ?? t.common.none}</span> {optionLabel(t.labels, activity.action) || activity.action}{' '}
                                {activity.description}
                            </li>
                        ))}
                    </ul>
                )}
            </Card>
        </>
    );
}

function Related({
    title,
    rows,
    empty,
}: {
    title: string;
    rows: { id: string; href: string; label: string; extra: ReactNode }[];
    empty: string;
}) {
    return (
        <Card className="mb-6 p-5">
            <h2 className="mb-3 font-medium">{title}</h2>
            {rows.length === 0 ? (
                <p className="text-sm text-zinc-500">{empty}</p>
            ) : (
                <ul className="space-y-2 text-sm">
                    {rows.map((row) => (
                        <li key={row.id} className="flex items-center justify-between gap-3">
                            <Link href={row.href} className="text-teal-800">
                                {row.label}
                            </Link>
                            {row.extra}
                        </li>
                    ))}
                </ul>
            )}
        </Card>
    );
}

Show.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
