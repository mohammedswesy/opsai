import { Card, CardForm } from '@/components/Card';
import { Button, ButtonLink } from '@/components/Button';
import { router, useForm } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { FormField, controlClassName } from '@/components/FormField';
import { PageHeader } from '@/components/PageHeader';
import { PriorityBadge } from '@/components/PriorityBadge';
import { StatusBadge } from '@/components/StatusBadge';
import { useI18n } from '@/i18n';
import { optionLabel } from '@/lib/labels';
import AppLayout from '@/layouts/AppLayout';

export default function Show({
    workOrder,
    invoice,
    transitions,
    canTransition,
    canInvoice,
}: {
    workOrder: {
        id: string;
        title: string;
        description: string | null;
        priority: string;
        status: string;
        technician_notes: string | null;
        completion_notes: string | null;
        customer: { name: string } | null;
        technician: { user: { name: string } | null } | null;
        service_request: { id: string; subject: string } | null;
    };
    invoice: { id: string; invoice_number: string } | null;
    transitions: string[];
    canTransition: boolean;
    canInvoice: boolean;
}) {
    const { t } = useI18n();
    const form = useForm({ status: transitions[0] ?? '', notes: '' });
    const [confirming, setConfirming] = useState(false);
    const needsConfirm = form.data.status === 'completed' || form.data.status === 'cancelled';

    return (
        <>
            <PageHeader
                title={workOrder.title}
                action={
                    canInvoice && workOrder.status === 'completed' ? (
                        invoice ? (
                            <ButtonLink variant="primary" href={`/invoices/${invoice.id}`}>
                                {invoice.invoice_number}
                            </ButtonLink>
                        ) : (
                            <Button variant="primary" type="button" onClick={() => router.post(`/work-orders/${workOrder.id}/invoice`)}>
                                {t.finance.generateInvoice}
                            </Button>
                        )
                    ) : null
                }
            />
            <Card className="mb-6 grid gap-3 p-5 sm:grid-cols-2">
                <p>{workOrder.customer?.name}</p>
                <p>{workOrder.technician?.user?.name ?? t.common.none}</p>
                <PriorityBadge value={workOrder.priority} />
                <StatusBadge value={workOrder.status} />
                <p>{workOrder.description}</p>
                {workOrder.service_request ? <p>{workOrder.service_request.subject}</p> : null}
            </Card>
            {canTransition && transitions.length > 0 ? (
                <CardForm className="max-w-xl space-y-4 p-5" onSubmit={(event) => {
                        event.preventDefault();
                        if (needsConfirm) {
                            setConfirming(true);
                            return;
                        }
                        form.post(`/work-orders/${workOrder.id}/transition`);
                    }}
                >
                    <h2 className="font-medium">{t.ops.transitions}</h2>
                    <FormField label={t.ops.status} error={form.errors.status}>
                        <select className={controlClassName} value={form.data.status} onChange={(event) => form.setData('status', event.target.value)}>
                            {transitions.map((status) => (
                                <option key={status} value={status}>
                                    {optionLabel(t.labels, status)}
                                </option>
                            ))}
                        </select>
                    </FormField>
                    <FormField label={t.ops.completionNotes} error={form.errors.notes}>
                        <textarea className={controlClassName} value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} />
                    </FormField>
                    <Button variant="primary" type="submit" disabled={form.processing}>
                        {t.common.save}
                    </Button>
                </CardForm>
            ) : null}
            <ConfirmDialog
                open={confirming}
                title={t.ops.confirmStatus}
                confirmLabel={t.common.confirm}
                cancelLabel={t.common.cancel}
                onCancel={() => setConfirming(false)}
                onConfirm={() => {
                    setConfirming(false);
                    form.post(`/work-orders/${workOrder.id}/transition`);
                }}
            />
        </>
    );
}

Show.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
