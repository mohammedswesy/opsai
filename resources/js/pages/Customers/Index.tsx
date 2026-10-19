import { router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { Button, ButtonLink } from '@/components/Button';
import { Chip } from '@/components/Chip';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { DataTable } from '@/components/DataTable';
import { FilterBar } from '@/components/FilterBar';
import { controlClassName } from '@/components/FormField';
import { PageHeader } from '@/components/PageHeader';
import { Pagination } from '@/components/Pagination';
import { RecordList } from '@/components/RecordList';
import { SearchInput } from '@/components/SearchInput';
import { StatusBadge } from '@/components/StatusBadge';
import { useI18n } from '@/i18n';
import AppLayout from '@/layouts/AppLayout';
import type { Option, Paginator } from '@/types/page';

type CustomerRow = {
    id: string;
    name: string;
    phone: string;
    type: string;
    city: string | null;
    status: string;
    branch: { name: string } | null;
};

export default function Index({
    customers,
    filters,
    branches,
    canManage,
}: {
    customers: Paginator<CustomerRow>;
    filters: { search?: string; status?: string; type?: string; branch_id?: string };
    branches: Option[];
    canManage: boolean;
}) {
    const { t } = useI18n();
    const [archiveId, setArchiveId] = useState<string | null>(null);

    return (
        <>
            <PageHeader title={t.nav.customers} action={canManage ? <ButtonLink href="/customers/create">{t.ops.create}</ButtonLink> : null} />
            <FilterBar>
                <SearchInput defaultValue={filters.search} placeholder={t.common.search} />
                <select className={controlClassName} name="type" defaultValue={filters.type ?? ''} aria-label={t.ops.type}>
                    <option value="">{t.ops.type}</option>
                    <option value="individual">{t.labels.individual}</option>
                    <option value="company">{t.labels.company}</option>
                </select>
                <select className={controlClassName} name="status" defaultValue={filters.status ?? ''} aria-label={t.ops.status}>
                    <option value="">{t.ops.status}</option>
                    <option value="active">{t.labels.active}</option>
                    <option value="inactive">{t.labels.inactive}</option>
                </select>
                <select className={controlClassName} name="branch_id" defaultValue={filters.branch_id ?? ''} aria-label={t.ops.branch}>
                    <option value="">{t.ops.branch}</option>
                    {branches.map((branch) => (
                        <option key={branch.id} value={branch.id}>
                            {branch.name}
                        </option>
                    ))}
                </select>
                <Button variant="primary" className="md:col-span-4" type="submit">
                    {t.common.search}
                </Button>
            </FilterBar>
            <RecordList empty={customers.data.length === 0}>
                <DataTable headers={[t.ops.customer, t.ops.phone, t.ops.type, t.ops.city, t.ops.branch, t.ops.status, '']}>
                    {customers.data.map((customer) => (
                        <tr key={customer.id}>
                            <td className="px-4 py-3 font-medium text-zinc-900">{customer.name}</td>
                            <td className="px-4 py-3 tabular-nums">{customer.phone}</td>
                            <td className="px-4 py-3">
                                <Chip>{customer.type === 'company' ? t.labels.company : t.labels.individual}</Chip>
                            </td>
                            <td className="px-4 py-3">{customer.city ?? t.common.none}</td>
                            <td className="px-4 py-3">{customer.branch?.name ?? t.common.none}</td>
                            <td className="px-4 py-3">
                                <StatusBadge value={customer.status} />
                            </td>
                            <td className="px-4 py-3 text-end">
                                <div className="flex justify-end gap-1">
                                    <ButtonLink href={`/customers/${customer.id}`} variant="ghost">
                                        {t.common.view}
                                    </ButtonLink>
                                    {canManage ? (
                                        <Button variant="ghost" onClick={() => setArchiveId(customer.id)}>
                                            {t.common.archive}
                                        </Button>
                                    ) : null}
                                </div>
                            </td>
                        </tr>
                    ))}
                </DataTable>
            </RecordList>
            <Pagination links={customers.links} />
            <ConfirmDialog
                open={archiveId !== null}
                title={t.ops.archiveConfirm}
                confirmLabel={t.common.archive}
                cancelLabel={t.common.cancel}
                onCancel={() => setArchiveId(null)}
                onConfirm={() => {
                    if (archiveId) {
                        router.delete(`/customers/${archiveId}`);
                    }
                    setArchiveId(null);
                }}
            />
        </>
    );
}

Index.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
