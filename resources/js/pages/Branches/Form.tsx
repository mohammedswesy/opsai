import { Button } from '@/components/Button';
import { Card, CardForm } from '@/components/Card';
import { Link, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { controlClassName, FormField } from '@/components/FormField';
import { PageHeader } from '@/components/PageHeader';
import { useI18n } from '@/i18n';
import AppLayout from '@/layouts/AppLayout';

type BranchFormData = {
    id: string;
    name: string;
    code: string;
    address: string | null;
    city: string | null;
    country: string;
    latitude: string | null;
    longitude: string | null;
    phone: string | null;
    timezone: string;
    status: string;
};

export default function BranchForm({
    branch,
    countries,
    statuses,
}: {
    branch: BranchFormData | null;
    countries: Record<string, string>;
    statuses: string[];
}) {
    const { t } = useI18n();
    const form = useForm({
        name: branch?.name ?? '',
        code: branch?.code ?? '',
        address: branch?.address ?? '',
        city: branch?.city ?? '',
        country: branch?.country ?? 'SA',
        latitude: branch?.latitude ?? '',
        longitude: branch?.longitude ?? '',
        phone: branch?.phone ?? '',
        timezone: branch?.timezone ?? 'Asia/Riyadh',
        status: branch?.status ?? 'active',
    });

    return (
        <>
            <PageHeader title={branch ? t.branches.edit : t.branches.create} />
            <CardForm className="max-w-2xl space-y-4 p-5" onSubmit={(event) => {
                    event.preventDefault();
                    if (branch) {
                        form.put(`/branches/${branch.id}`);
                        return;
                    }
                    form.post('/branches');
                }}
            >
                <FormField label={t.branches.name} error={form.errors.name}>
                    <input className={controlClassName} value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />
                </FormField>
                <FormField label={t.branches.code} error={form.errors.code}>
                    <input className={controlClassName} value={form.data.code} onChange={(event) => form.setData('code', event.target.value)} />
                </FormField>
                <FormField label={t.branches.address} error={form.errors.address}>
                    <input className={controlClassName} value={form.data.address} onChange={(event) => form.setData('address', event.target.value)} />
                </FormField>
                <FormField label={t.branches.city} error={form.errors.city}>
                    <input className={controlClassName} value={form.data.city} onChange={(event) => form.setData('city', event.target.value)} />
                </FormField>
                <FormField label={t.branches.country} error={form.errors.country}>
                    <select className={controlClassName} value={form.data.country} onChange={(event) => form.setData('country', event.target.value)}>
                        {Object.entries(countries).map(([code, label]) => (
                            <option key={code} value={code}>
                                {label}
                            </option>
                        ))}
                    </select>
                </FormField>
                <FormField label={t.branches.phone} error={form.errors.phone}>
                    <input className={controlClassName} value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} />
                </FormField>
                <FormField label={t.branches.timezone} error={form.errors.timezone}>
                    <input className={controlClassName} value={form.data.timezone} onChange={(event) => form.setData('timezone', event.target.value)} />
                </FormField>
                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField label={t.branches.latitude} error={form.errors.latitude}>
                        <input className={controlClassName} value={form.data.latitude} onChange={(event) => form.setData('latitude', event.target.value)} />
                    </FormField>
                    <FormField label={t.branches.longitude} error={form.errors.longitude}>
                        <input className={controlClassName} value={form.data.longitude} onChange={(event) => form.setData('longitude', event.target.value)} />
                    </FormField>
                </div>
                <FormField label={t.branches.status} error={form.errors.status}>
                    <select className={controlClassName} value={form.data.status} onChange={(event) => form.setData('status', event.target.value)}>
                        {statuses.map((status) => (
                            <option key={status} value={status}>
                                {status}
                            </option>
                        ))}
                    </select>
                </FormField>
                <div className="flex gap-3">
                    <Button variant="primary" type="submit" disabled={form.processing}>
                        {t.branches.save}
                    </Button>
                    <Link href="/branches" className="rounded-md px-4 py-2 text-sm text-zinc-700">
                        {t.common.cancel}
                    </Link>
                </div>
            </CardForm>
        </>
    );
}

BranchForm.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
