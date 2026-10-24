import type { ReactNode } from 'react';
import { ButtonLink } from '@/components/Button';
import { DataTable, type Column } from '@/components/DataTable';
import { FilterBar, activeFilters } from '@/components/FilterBar';
import { controlClassName } from '@/components/FormField';
import { PageHeader } from '@/components/PageHeader';
import { Pagination } from '@/components/Pagination';
import { PriorityBadge } from '@/components/PriorityBadge';
import { RecordList } from '@/components/RecordList';
import { SearchInput } from '@/components/SearchInput';
import { StatusBadge } from '@/components/StatusBadge';
import { useI18n } from '@/i18n';
import AppLayout from '@/layouts/AppLayout';
import { useFormat } from '@/lib/format';
import { optionLabel } from '@/lib/labels';
import { PRIORITIES, TASK_STATUSES } from '@/lib/options';
import type { Option, Paginator } from '@/types/page';

type TaskRow = {
    id: string;
    title: string;
    priority: string;
    status: string;
    due_at: string | null;
    assignee: { name: string } | null;
    latest_activity: { action: string } | null;
};

type Filters = { search?: string; status?: string; priority?: string; branch_id?: string };

const OPEN_STATUSES = ['todo', 'in_progress'];

export default function Index({
    tasks,
    filters,
    branches = [],
    canManage,
}: {
    tasks: Paginator<TaskRow>;
    filters: Filters;
    branches?: Option[];
    canManage: boolean;
}) {
    const { t } = useI18n();
    const format = useFormat();
    const label = (value: string) => optionLabel(t.labels, value);
    const now = Date.now();
    const active = activeFilters(filters, {
        search: { label: t.common.search },
        status: { label: t.ops.status, value: label },
        priority: { label: t.ops.priority, value: label },
        branch_id: { label: t.ops.branch, value: (value) => branches.find((branch) => branch.id === value)?.name ?? value },
    });

    const columns: Column<TaskRow>[] = [
        {
            key: 'title',
            header: t.ops.title,
            primary: true,
            isolate: true,
            cell: (row) => (
                <>
                    <span className="block max-w-72 truncate" title={row.title}>
                        {row.title}
                    </span>
                    {row.latest_activity ? <span className="block text-caption font-normal text-ink-muted">{label(row.latest_activity.action)}</span> : null}
                </>
            ),
        },
        { key: 'assignee', header: t.ops.assignee, truncate: (row) => row.assignee?.name, cell: (row) => row.assignee?.name ?? t.common.none },
        { key: 'priority', header: t.ops.priority, cell: (row) => <PriorityBadge value={row.priority} /> },
        { key: 'status', header: t.ops.status, cell: (row) => <StatusBadge value={row.status} /> },
        {
            key: 'due',
            header: t.ops.due,
            cell: (row) => {
                if (!row.due_at) {
                    return <span className="text-ink-subtle">{t.common.none}</span>;
                }

                const overdue = OPEN_STATUSES.includes(row.status) && new Date(row.due_at).getTime() < now;

                return <span className={`md:whitespace-nowrap tabular-nums ${overdue ? 'font-medium text-danger' : ''}`}>{format.dateTime(row.due_at)}</span>;
            },
        },
    ];

    return (
        <>
            <PageHeader
                title={t.nav.tasks}
                action={
                    canManage ? (
                        <ButtonLink variant="primary" href="/tasks/create">
                            {t.ops.create}
                        </ButtonLink>
                    ) : null
                }
            />
            <FilterBar search={<SearchInput defaultValue={filters.search} placeholder={t.common.search} />} active={active}>
                <select className={controlClassName} name="status" defaultValue={filters.status ?? ''} aria-label={t.ops.status}>
                    <option value="">{t.ops.status}</option>
                    {TASK_STATUSES.map((value) => (
                        <option key={value} value={value}>
                            {label(value)}
                        </option>
                    ))}
                </select>
                <select className={controlClassName} name="priority" defaultValue={filters.priority ?? ''} aria-label={t.ops.priority}>
                    <option value="">{t.ops.priority}</option>
                    {PRIORITIES.map((value) => (
                        <option key={value} value={value}>
                            {label(value)}
                        </option>
                    ))}
                </select>
                {branches.length > 0 ? (
                    <select className={controlClassName} name="branch_id" defaultValue={filters.branch_id ?? ''} aria-label={t.ops.branch}>
                        <option value="">{t.ops.branch}</option>
                        {branches.map((branch) => (
                            <option key={branch.id} value={branch.id}>
                                {branch.name}
                            </option>
                        ))}
                    </select>
                ) : null}
            </FilterBar>
            <RecordList empty={tasks.data.length === 0} filtered={active.length > 0} loading="inline">
                <DataTable
                    caption={t.nav.tasks}
                    columns={columns}
                    rows={tasks.data}
                    rowKey={(row) => row.id}
                    actions={canManage ? (row) => [{ key: 'edit', label: t.common.edit, href: `/tasks/${row.id}/edit`, inline: true }] : undefined}
                />
                <Pagination paginator={tasks} />
            </RecordList>
        </>
    );
}

Index.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
