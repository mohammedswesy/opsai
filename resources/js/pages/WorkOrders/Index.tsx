import type { ReactNode } from 'react';
import { Button, ButtonLink } from '@/components/Button';
import { DataTable } from '@/components/DataTable';
import { FilterBar } from '@/components/FilterBar';
import { controlClassName } from '@/components/FormField';
import { PageHeader } from '@/components/PageHeader';
import { Pagination } from '@/components/Pagination';
import { PriorityBadge } from '@/components/PriorityBadge';
import { RecordList } from '@/components/RecordList';
import { SearchInput } from '@/components/SearchInput';
import { StatusBadge } from '@/components/StatusBadge';
import { useI18n } from '@/i18n';
import { optionLabel } from '@/lib/labels';
import AppLayout from '@/layouts/AppLayout';
import type { Option, Paginator } from '@/types/page';

type OrderRow = {
    id: string;
    title: string;
    status: string;
    priority: string;
    customer: { name: string } | null;
    technician: { user: { name: string } | null } | null;
};

export default function Index({
    workOrders,
    filters,
    branches,
    canManage,
}: {
    workOrders: Paginator<OrderRow>;
    filters: { search?: string; status?: string; priority?: string; branch_id?: string };
    branches: Option[];
    canManage: boolean;
}) {
    const { t } = useI18n();

    return (
        <>
            <PageHeader title={t.nav.workOrders} action={canManage ? <ButtonLink href="/work-orders/create">{t.ops.create}</ButtonLink> : null} />
            <FilterBar>
                <SearchInput defaultValue={filters.search} placeholder={t.common.search} />
                <select className={controlClassName} name="status" defaultValue={filters.status ?? ''} aria-label={t.ops.status}>
                    <option value="">{t.ops.status}</option>
                    {['assigned', 'accepted', 'on_the_way', 'arrived', 'in_progress', 'completed', 'cancelled'].map((value) => (
                        <option key={value} value={value}>
                            {optionLabel(t.labels, value)}
                        </option>
                    ))}
                </select>
                <select className={controlClassName} name="priority" defaultValue={filters.priority ?? ''} aria-label={t.ops.priority}>
                    <option value="">{t.ops.priority}</option>
                    {['low', 'normal', 'high', 'urgent'].map((value) => (
                        <option key={value} value={value}>
                            {optionLabel(t.labels, value)}
                        </option>
                    ))}
                </select>
                <select className={controlClassName} name="branch_id" defaultValue={filters.branch_id ?? ''} aria-label={t.ops.branch}>
                    <option value="">{t.ops.branch}</option>
                    {branches.map((branch) => (
                        <option key={branch.id} value={branch.id}>
                            {branch.name}
                        </option>
                    ))}
                </select>
                <Button variant="primary" type="submit">
                    {t.common.search}
                </Button>
            </FilterBar>
            <RecordList empty={workOrders.data.length === 0}>
                <DataTable headers={[t.ops.title, t.ops.customer, t.ops.technician, t.ops.priority, t.ops.status, '']}>
                    {workOrders.data.map((order) => (
                        <tr key={order.id}>
                            <td className="px-4 py-3 font-medium text-zinc-900">{order.title}</td>
                            <td className="px-4 py-3">{order.customer?.name}</td>
                            <td className="px-4 py-3">{order.technician?.user?.name ?? t.common.none}</td>
                            <td className="px-4 py-3">
                                <PriorityBadge value={order.priority} />
                            </td>
                            <td className="px-4 py-3">
                                <StatusBadge value={order.status} />
                            </td>
                            <td className="px-4 py-3 text-end">
                                <ButtonLink href={`/work-orders/${order.id}`} variant="ghost">
                                    {t.common.view}
                                </ButtonLink>
                            </td>
                        </tr>
                    ))}
                </DataTable>
            </RecordList>
            <Pagination links={workOrders.links} />
        </>
    );
}

Index.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
