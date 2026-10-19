import { Button, ButtonLink } from '@/components/Button';
import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { DataTable } from '@/components/DataTable';
import { EmptyState } from '@/components/EmptyState';
import { FilterBar } from '@/components/FilterBar';
import { PageHeader } from '@/components/PageHeader';
import { Pagination } from '@/components/Pagination';
import { PriorityBadge } from '@/components/PriorityBadge';
import { SearchInput } from '@/components/SearchInput';
import { StatusBadge } from '@/components/StatusBadge';
import { useI18n } from '@/i18n';
import { optionLabel } from '@/lib/labels';
import AppLayout from '@/layouts/AppLayout';
import type { Paginator } from '@/types/page';

type TaskRow = {
    id: string;
    title: string;
    priority: string;
    status: string;
    due_at: string | null;
    assignee: { name: string } | null;
    latest_activity: { action: string } | null;
};

export default function Index({ tasks, filters, canManage }: { tasks: Paginator<TaskRow>; filters: { search?: string; status?: string }; canManage: boolean }) {
    const { t } = useI18n();

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
            <FilterBar>
                <SearchInput defaultValue={filters.search} placeholder={t.common.search} />
                <select className="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm" name="status" defaultValue={filters.status ?? ''}>
                    <option value="">{t.ops.status}</option>
                    <option value="todo">{t.labels.todo}</option>
                    <option value="in_progress">{t.labels.in_progress}</option>
                    <option value="completed">{t.labels.completed}</option>
                    <option value="cancelled">{t.labels.cancelled}</option>
                </select>
                <Button variant="primary" type="submit">
                    {t.common.search}
                </Button>
            </FilterBar>
            {tasks.data.length === 0 ? (
                <EmptyState title={t.ops.empty} />
            ) : (
                <DataTable headers={[t.ops.title, t.ops.assignee, t.ops.priority, t.ops.status, t.ops.due, '']}>
                    {tasks.data.map((task) => (
                        <tr key={task.id} className="border-b border-zinc-100">
                            <td className="px-4 py-3 font-medium">{task.title}</td>
                            <td className="px-4 py-3">
                                {task.assignee?.name ?? t.common.none}
                                {task.latest_activity ? <span className="mt-1 block text-xs text-zinc-500">{optionLabel(t.labels, task.latest_activity.action)}</span> : null}
                            </td>
                            <td className="px-4 py-3">
                                <PriorityBadge value={task.priority} />
                            </td>
                            <td className="px-4 py-3">
                                <StatusBadge value={task.status} />
                            </td>
                            <td className="px-4 py-3">{task.due_at ?? t.common.none}</td>
                            <td className="px-4 py-3 text-end">
                                {canManage ? (
                                    <Link href={`/tasks/${task.id}/edit`} className="text-teal-800">
                                        {t.common.edit}
                                    </Link>
                                ) : null}
                            </td>
                        </tr>
                    ))}
                </DataTable>
            )}
            <Pagination links={tasks.links} />
        </>
    );
}

Index.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
