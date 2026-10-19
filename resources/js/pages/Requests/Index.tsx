import { Button, ButtonLink } from '@/components/Button';
import { Chip } from '@/components/Chip';
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
import type { ReactNode } from 'react';

type RequestRow = {
    id: string;
    subject: string;
    status: string;
    priority: string;
    source: string;
    customer: { name: string } | null;
};

export default function Index({
    requests,
    filters,
    branches,
    canManage,
}: {
    requests: Paginator<RequestRow>;
    filters: { search?: string; status?: string; priority?: string; source?: string; branch_id?: string };
    branches: Option[];
    canManage: boolean;
}) {
    const { t } = useI18n();

    return (
        <>
            <PageHeader title={t.nav.requests} action={canManage ? <ButtonLink href="/requests/create">{t.ops.create}</ButtonLink> : null} />
            <FilterBar>
                <SearchInput defaultValue={filters.search} placeholder={t.common.search} />
                <select className={controlClassName} name="status" defaultValue={filters.status ?? ''} aria-label={t.ops.status}>
                    <option value="">{t.ops.status}</option>
                    {['new', 'qualified', 'scheduled', 'in_progress', 'completed', 'cancelled'].map((value) => (
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
                <Button variant="primary" className="md:col-span-4" type="submit">
                    {t.common.search}
                </Button>
            </FilterBar>
            <RecordList empty={requests.data.length === 0}>
                <DataTable headers={[t.ops.subject, t.ops.customer, t.ops.priority, t.ops.status, t.ops.source, '']}>
                    {requests.data.map((request) => (
                        <tr key={request.id}>
                            <td className="px-4 py-3 font-medium text-zinc-900">{request.subject}</td>
                            <td className="px-4 py-3">{request.customer?.name}</td>
                            <td className="px-4 py-3">
                                <PriorityBadge value={request.priority} />
                            </td>
                            <td className="px-4 py-3">
                                <StatusBadge value={request.status} />
                            </td>
                            <td className="px-4 py-3">
                                <Chip>{optionLabel(t.labels, request.source)}</Chip>
                            </td>
                            <td className="px-4 py-3 text-end">
                                <ButtonLink href={`/requests/${request.id}`} variant="ghost">
                                    {t.common.view}
                                </ButtonLink>
                            </td>
                        </tr>
                    ))}
                </DataTable>
            </RecordList>
            <Pagination links={requests.links} />
        </>
    );
}

Index.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
