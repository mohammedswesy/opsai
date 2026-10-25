import { Button } from '@/components/Button';
import { Card, CardForm } from '@/components/Card';
import { Link, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { FormField, controlClassName } from '@/components/FormField';
import { FormPage } from '@/components/FormPage';
import { PageHeader } from '@/components/PageHeader';
import { useI18n } from '@/i18n';
import AppLayout from '@/layouts/AppLayout';

type CatalogItem = {
    id: string;
    name: string;
    description: string | null;
    category: string | null;
    base_price: string | null;
    estimated_duration: number | null;
    status: string;
};

export default function Form({ service }: { service: CatalogItem | null }) {
    const { t } = useI18n();
    const form = useForm({
        name: service?.name ?? '',
        description: service?.description ?? '',
        category: service?.category ?? '',
        base_price: service?.base_price ?? '',
        estimated_duration: service?.estimated_duration?.toString() ?? '',
        status: service?.status ?? 'active',
    });

    return (
        <FormPage>
            <PageHeader parent={{ label: t.nav.services, href: '/services' }} title={service ? t.common.edit : t.ops.create} subtitle={t.nav.services} />
            <CardForm className="grid gap-4 p-5" onSubmit={(event) => {
                    event.preventDefault();
                    const payload = {
                        ...form.data,
                        base_price: form.data.base_price === '' ? null : form.data.base_price,
                        estimated_duration: form.data.estimated_duration === '' ? null : form.data.estimated_duration,
                    };
                    form.transform(() => payload);
                    if (service) {
                        form.put(`/services/${service.id}`);
                    } else {
                        form.post('/services');
                    }
                }}
            >
                <FormField label={t.ops.service} error={form.errors.name}>
                    <input className={controlClassName} value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />
                </FormField>
                <FormField label={t.ops.description} error={form.errors.description}>
                    <textarea className={controlClassName} value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} />
                </FormField>
                <FormField label={t.ops.category} error={form.errors.category}>
                    <input className={controlClassName} value={form.data.category} onChange={(event) => form.setData('category', event.target.value)} />
                </FormField>
                <FormField label={t.ops.price} error={form.errors.base_price}>
                    <input className={controlClassName} value={form.data.base_price} onChange={(event) => form.setData('base_price', event.target.value)} />
                </FormField>
                <FormField label={t.ops.duration} error={form.errors.estimated_duration}>
                    <input className={controlClassName} value={form.data.estimated_duration} onChange={(event) => form.setData('estimated_duration', event.target.value)} />
                </FormField>
                <FormField label={t.ops.status} error={form.errors.status}>
                    <select className={controlClassName} value={form.data.status} onChange={(event) => form.setData('status', event.target.value)}>
                        <option value="active">{t.labels.active}</option>
                        <option value="inactive">{t.labels.inactive}</option>
                    </select>
                </FormField>
                <div className="flex gap-3">
                    <Button variant="primary" type="submit" disabled={form.processing}>
                        {t.common.save}
                    </Button>
                    <Link href="/services" className="px-4 py-2 text-sm text-zinc-700">
                        {t.common.cancel}
                    </Link>
                </div>
            </CardForm>
        </FormPage>
    );
}

Form.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
