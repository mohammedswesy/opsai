import { Button, ButtonLink } from '@/components/Button';
import { Link, router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { DataTable } from '@/components/DataTable';
import { EmptyState } from '@/components/EmptyState';
import { FilterBar } from '@/components/FilterBar';
import { PageHeader } from '@/components/PageHeader';
import { Pagination } from '@/components/Pagination';
import { SearchInput } from '@/components/SearchInput';
import { StatusBadge } from '@/components/StatusBadge';
import { useI18n } from '@/i18n';
import AppLayout from '@/layouts/AppLayout';
import { useFormat } from '@/lib/format';
import type { Paginator } from '@/types/page';

type ServiceRow = {
    id: string;
    name: string;
    category: string | null;
    base_price: string | null;
    estimated_duration: number | null;
    status: string;
};

export default function Index({
    services,
    filters,
    canManage,
}: {
    services: Paginator<ServiceRow>;
    filters: { search?: string; status?: string; category?: string };
    canManage: boolean;
}) {
    const { t } = useI18n();
    const format = useFormat();
    const [archiveId, setArchiveId] = useState<string | null>(null);

    return (
        <>
            <PageHeader
                title={t.nav.services}
                action={
                    canManage ? (
                        <ButtonLink variant="primary" href="/services/create">
                            {t.ops.create}
                        </ButtonLink>
                    ) : null
                }
            />
            <FilterBar>
                <SearchInput defaultValue={filters.search} placeholder={t.common.search} />
                <SearchInput name="category" defaultValue={filters.category} placeholder={t.ops.category} />
                <select className="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm" name="status" defaultValue={filters.status ?? ''}>
                    <option value="">{t.ops.status}</option>
                    <option value="active">{t.labels.active}</option>
                    <option value="inactive">{t.labels.inactive}</option>
                </select>
                <Button variant="primary" type="submit">
                    {t.common.search}
                </Button>
            </FilterBar>
            {services.data.length === 0 ? (
                <EmptyState title={t.ops.empty} />
            ) : (
                <DataTable headers={[t.ops.service, t.ops.category, t.ops.price, t.ops.estimatedDuration, t.ops.status, '']}>
                    {services.data.map((service) => (
                        <tr key={service.id} className="border-b border-zinc-100">
                            <td className="px-4 py-3 font-medium">{service.name}</td>
                            <td className="px-4 py-3">{service.category ?? t.common.none}</td>
                            <td className="px-4 py-3 tabular-nums">{service.base_price !== null ? format.money(service.base_price) : t.common.none}</td>
                            <td className="px-4 py-3 tabular-nums">{service.estimated_duration !== null ? format.duration(service.estimated_duration) : t.common.none}</td>
                            <td className="px-4 py-3">
                                <StatusBadge value={service.status} />
                            </td>
                            <td className="px-4 py-3 text-end">
                                {canManage ? (
                                    <>
                                        <Link href={`/services/${service.id}/edit`} className="text-teal-800">
                                            {t.common.edit}
                                        </Link>
                                        <Button variant="ghost" onClick={() => setArchiveId(service.id)}>
                                            {t.common.archive}
                                        </Button>
                                    </>
                                ) : null}
                            </td>
                        </tr>
                    ))}
                </DataTable>
            )}
            <Pagination links={services.links} />
            <ConfirmDialog
                open={archiveId !== null}
                title={t.ops.archiveConfirm}
                confirmLabel={t.common.archive}
                cancelLabel={t.common.cancel}
                onCancel={() => setArchiveId(null)}
                onConfirm={() => {
                    if (archiveId) {
                        router.delete(`/services/${archiveId}`);
                    }
                    setArchiveId(null);
                }}
            />
        </>
    );
}

Index.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
