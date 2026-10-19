import { Button } from '@/components/Button';
import { Card, CardForm } from '@/components/Card';
import { Link, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { FormField, controlClassName } from '@/components/FormField';
import { PageHeader } from '@/components/PageHeader';
import { useI18n } from '@/i18n';
import { optionLabel } from '@/lib/labels';
import AppLayout from '@/layouts/AppLayout';

export default function Form({
    invoices,
    defaults,
}: {
    invoices: { id: string; invoice_number: string; amount_due: string; currency: string }[];
    defaults: { invoice_id: string };
}) {
    const { t } = useI18n();
    const form = useForm({
        invoice_id: defaults.invoice_id,
        amount: '',
        method: 'cash',
        reference: '',
        notes: '',
    });

    return (
        <>
            <PageHeader title={t.finance.recordPayment} />
            <CardForm className="grid max-w-xl gap-4 p-5" onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/payments');
                }}
            >
                <FormField label={t.nav.invoices} error={form.errors.invoice_id}>
                    <select className={controlClassName} value={form.data.invoice_id} onChange={(event) => form.setData('invoice_id', event.target.value)}>
                        <option value="">{t.common.none}</option>
                        {invoices.map((invoice) => (
                            <option key={invoice.id} value={invoice.id}>
                                {invoice.invoice_number} ({invoice.amount_due} {invoice.currency})
                            </option>
                        ))}
                    </select>
                </FormField>
                {form.data.invoice_id ? (
                    <p className="text-sm text-zinc-600">
                        {t.finance.outstanding}: <span className="text-lg font-semibold tabular-nums text-zinc-900">{invoices.find((invoice) => invoice.id === form.data.invoice_id)?.amount_due} {invoices.find((invoice) => invoice.id === form.data.invoice_id)?.currency}</span>
                    </p>
                ) : null}
                <FormField label={t.finance.amount} error={form.errors.amount}>
                    <input className={controlClassName} value={form.data.amount} onChange={(event) => form.setData('amount', event.target.value)} />
                </FormField>
                <FormField label={t.finance.method} error={form.errors.method}>
                    <select className={controlClassName} value={form.data.method} onChange={(event) => form.setData('method', event.target.value)}>
                        {['cash', 'bank_transfer', 'card', 'online', 'other'].map((method) => (
                            <option key={method} value={method}>
                                {optionLabel(t.labels, method)}
                            </option>
                        ))}
                    </select>
                </FormField>
                <FormField label={t.finance.reference} error={form.errors.reference}>
                    <input className={controlClassName} value={form.data.reference} onChange={(event) => form.setData('reference', event.target.value)} />
                </FormField>
                <FormField label={t.common.notes} error={form.errors.notes}>
                    <textarea className={controlClassName} value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} />
                </FormField>
                <div className="flex gap-3">
                    <Button variant="primary" type="submit" disabled={form.processing}>
                        {t.common.save}
                    </Button>
                    <Link href="/payments" className="px-4 py-2 text-sm text-zinc-700">
                        {t.common.cancel}
                    </Link>
                </div>
            </CardForm>
        </>
    );
}

Form.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
