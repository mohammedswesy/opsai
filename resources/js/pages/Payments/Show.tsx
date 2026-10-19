import { Button } from '@/components/Button';
import { Card } from '@/components/Card';
import { Link, router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { PageHeader } from '@/components/PageHeader';
import { StatusBadge } from '@/components/StatusBadge';
import { useI18n } from '@/i18n';
import AppLayout from '@/layouts/AppLayout';

export default function Show({
    payment,
    canRefund,
}: {
    payment: {
        id: string;
        amount: string;
        currency: string;
        method: string;
        status: string;
        reference: string | null;
        paid_at: string;
        notes: string | null;
        invoice: { id: string; invoice_number: string; customer: { name: string } | null } | null;
        creator: { name: string } | null;
    };
    canRefund: boolean;
}) {
    const { t } = useI18n();
    const [confirming, setConfirming] = useState(false);

    return (
        <>
            <PageHeader
                title={`${payment.amount} ${payment.currency}`}
                action={
                    canRefund && payment.status === 'completed' ? (
                        <Button variant="secondary" type="button" onClick={() => setConfirming(true)}>
                            {t.finance.refund}
                        </Button>
                    ) : null
                }
            />
            <Card className="grid gap-3 p-5 text-sm sm:grid-cols-2">
                <p>
                    {t.nav.invoices}: {payment.invoice ? <Link href={`/invoices/${payment.invoice.id}`}>{payment.invoice.invoice_number}</Link> : t.common.none}
                </p>
                <p>{payment.invoice?.customer?.name}</p>
                <StatusBadge value={payment.method} />
                <StatusBadge value={payment.status} />
                <p>{payment.reference ?? t.common.none}</p>
                <p>{payment.paid_at}</p>
                <p>{payment.creator?.name}</p>
                {payment.notes ? <p>{payment.notes}</p> : null}
            </Card>
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
