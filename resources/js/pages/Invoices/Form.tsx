import { Button } from '@/components/Button';
import { Card, CardForm } from '@/components/Card';
import { Link, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { FormField, controlClassName } from '@/components/FormField';
import { FormPage } from '@/components/FormPage';
import { PageHeader } from '@/components/PageHeader';
import { useI18n } from '@/i18n';
import AppLayout from '@/layouts/AppLayout';
import type { Option } from '@/types/page';

type Item = { description: string; quantity: string; unit_price: string; service_id: string };
type InvoiceForm = {
    id: string;
    customer_id: string;
    branch_id: string | null;
    work_order_id: string | null;
    due_date: string | null;
    discount: string;
    notes: string | null;
    items: Item[];
};

export default function Form({
    invoice,
    branches,
    customers,
    services,
    workOrders,
    defaults,
}: {
    invoice: InvoiceForm | null;
    branches: Option[];
    customers: Option[];
    services: Option[];
    workOrders: { id: string; title: string; customer_id: string }[];
    defaults: { customer_id: string; work_order_id: string };
}) {
    const { t } = useI18n();
    const form = useForm({
        customer_id: invoice?.customer_id ?? defaults.customer_id,
        branch_id: invoice?.branch_id ?? '',
        work_order_id: invoice?.work_order_id ?? defaults.work_order_id,
        due_date: invoice?.due_date?.slice(0, 10) ?? '',
        discount: invoice?.discount ?? '0',
        notes: invoice?.notes ?? '',
        items: invoice?.items?.map((item) => ({
            description: item.description,
            quantity: item.quantity,
            unit_price: item.unit_price,
            service_id: item.service_id ?? '',
        })) ?? [{ description: '', quantity: '1', unit_price: '0', service_id: '' }],
    });

    return (
        <FormPage size="wide">
            <PageHeader parent={{ label: t.nav.invoices, href: '/invoices' }} title={invoice ? t.common.edit : t.ops.create} subtitle={t.nav.invoices} />
            <CardForm className="grid gap-4 p-5" onSubmit={(event) => {
                    event.preventDefault();
                    const payload = {
                        ...form.data,
                        branch_id: form.data.branch_id || null,
                        work_order_id: form.data.work_order_id || null,
                        due_date: form.data.due_date || null,
                        items: form.data.items.map((item) => ({ ...item, service_id: item.service_id || null })),
                    };
                    form.transform(() => payload);
                    if (invoice) {
                        form.put(`/invoices/${invoice.id}`);
                    } else {
                        form.post('/invoices');
                    }
                }}
            >
                <FormField label={t.ops.customer} error={form.errors.customer_id}>
                    <select className={controlClassName} value={form.data.customer_id} onChange={(event) => form.setData('customer_id', event.target.value)}>
                        <option value="">{t.common.none}</option>
                        {customers.map((customer) => (
                            <option key={customer.id} value={customer.id}>
                                {customer.name}
                            </option>
                        ))}
                    </select>
                </FormField>
                <FormField label={t.ops.relatedWorkOrder} error={form.errors.work_order_id}>
                    <select className={controlClassName} value={form.data.work_order_id} onChange={(event) => form.setData('work_order_id', event.target.value)}>
                        <option value="">{t.common.none}</option>
                        {workOrders.map((order) => (
                            <option key={order.id} value={order.id}>
                                {order.title}
                            </option>
                        ))}
                    </select>
                </FormField>
                <FormField label={t.ops.branch} error={form.errors.branch_id}>
                    <select className={controlClassName} value={form.data.branch_id} onChange={(event) => form.setData('branch_id', event.target.value)}>
                        <option value="">{t.common.none}</option>
                        {branches.map((branch) => (
                            <option key={branch.id} value={branch.id}>
                                {branch.name}
                            </option>
                        ))}
                    </select>
                </FormField>
                <FormField label={t.finance.dueDate} error={form.errors.due_date}>
                    <input type="date" className={controlClassName} value={form.data.due_date} onChange={(event) => form.setData('due_date', event.target.value)} />
                </FormField>
                <FormField label={t.finance.discount} error={form.errors.discount}>
                    <input className={controlClassName} value={form.data.discount} onChange={(event) => form.setData('discount', event.target.value)} />
                </FormField>
                <div className="space-y-3">
                    <div className="flex items-center justify-between">
                        <p className="text-sm font-medium">{t.finance.items}</p>
                        <button
                            type="button"
                            className="text-sm text-teal-800"
                            onClick={() => form.setData('items', [...form.data.items, { description: '', quantity: '1', unit_price: '0', service_id: '' }])}
                        >
                            {t.finance.addItem}
                        </button>
                    </div>
                    {form.data.items.map((item, index) => (
                        <div key={index} className="grid gap-2 sm:grid-cols-4">
                            <input className={controlClassName} value={item.description} placeholder={t.ops.description} onChange={(event) => updateItem(index, 'description', event.target.value)} />
                            <input className={controlClassName} value={item.quantity} placeholder={t.finance.quantity} onChange={(event) => updateItem(index, 'quantity', event.target.value)} />
                            <input className={controlClassName} value={item.unit_price} placeholder={t.finance.unitPrice} onChange={(event) => updateItem(index, 'unit_price', event.target.value)} />
                            <select className={controlClassName} value={item.service_id} onChange={(event) => updateItem(index, 'service_id', event.target.value)}>
                                <option value="">{t.ops.service}</option>
                                {services.map((service) => (
                                    <option key={service.id} value={service.id}>
                                        {service.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                    ))}
                </div>
                <FormField label={t.common.notes} error={form.errors.notes}>
                    <textarea className={controlClassName} value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} />
                </FormField>
                <div className="flex gap-3">
                    <Button variant="primary" type="submit" disabled={form.processing}>
                        {t.common.save}
                    </Button>
                    <Link href="/invoices" className="px-4 py-2 text-sm text-zinc-700">
                        {t.common.cancel}
                    </Link>
                </div>
            </CardForm>
        </FormPage>
    );

    function updateItem(index: number, key: keyof Item, value: string) {
        const items = [...form.data.items];
        items[index] = { ...items[index], [key]: value };
        form.setData('items', items);
    }
}

Form.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
