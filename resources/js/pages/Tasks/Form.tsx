import { Button } from '@/components/Button';
import { Card, CardForm } from '@/components/Card';
import { Link, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { FormField, controlClassName } from '@/components/FormField';
import { PageHeader } from '@/components/PageHeader';
import { useI18n } from '@/i18n';
import { optionLabel } from '@/lib/labels';
import AppLayout from '@/layouts/AppLayout';
import type { Option } from '@/types/page';

type TaskForm = {
    id: string;
    branch_id: string | null;
    assigned_to: string | null;
    title: string;
    description: string | null;
    priority: string;
    status: string;
    due_at: string | null;
};

export default function Form({ task, branches, members }: { task: TaskForm | null; branches: Option[]; members: Option[] }) {
    const { t } = useI18n();
    const form = useForm({
        branch_id: task?.branch_id ?? '',
        assigned_to: task?.assigned_to ?? '',
        title: task?.title ?? '',
        description: task?.description ?? '',
        priority: task?.priority ?? 'normal',
        status: task?.status ?? 'todo',
        due_at: task?.due_at?.slice(0, 16) ?? '',
    });

    return (
        <>
            <PageHeader title={task ? t.common.edit : t.ops.create} subtitle={t.nav.tasks} />
            <CardForm className="grid max-w-3xl gap-4 p-5" onSubmit={(event) => {
                    event.preventDefault();
                    const payload = {
                        ...form.data,
                        branch_id: form.data.branch_id || null,
                        assigned_to: form.data.assigned_to || null,
                        due_at: form.data.due_at || null,
                    };
                    form.transform(() => payload);
                    if (task) {
                        form.put(`/tasks/${task.id}`);
                    } else {
                        form.post('/tasks');
                    }
                }}
            >
                <FormField label={t.ops.title} error={form.errors.title}>
                    <input className={controlClassName} value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} />
                </FormField>
                <FormField label={t.ops.description} error={form.errors.description}>
                    <textarea className={controlClassName} value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} />
                </FormField>
                <FormField label={t.ops.assignee} error={form.errors.assigned_to}>
                    <select className={controlClassName} value={form.data.assigned_to} onChange={(event) => form.setData('assigned_to', event.target.value)}>
                        <option value="">{t.common.none}</option>
                        {members.map((member) => (
                            <option key={member.id} value={member.id}>
                                {member.name}
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
                <FormField label={t.ops.priority} error={form.errors.priority}>
                    <select className={controlClassName} value={form.data.priority} onChange={(event) => form.setData('priority', event.target.value)}>
                        {['low', 'normal', 'high', 'urgent'].map((value) => (
                            <option key={value} value={value}>
                                {optionLabel(t.labels, value)}
                            </option>
                        ))}
                    </select>
                </FormField>
                <FormField label={t.ops.status} error={form.errors.status}>
                    <select className={controlClassName} value={form.data.status} onChange={(event) => form.setData('status', event.target.value)}>
                        {['todo', 'in_progress', 'completed', 'cancelled'].map((value) => (
                            <option key={value} value={value}>
                                {optionLabel(t.labels, value)}
                            </option>
                        ))}
                    </select>
                </FormField>
                <FormField label={t.ops.due} error={form.errors.due_at}>
                    <input type="datetime-local" className={controlClassName} value={form.data.due_at} onChange={(event) => form.setData('due_at', event.target.value)} />
                </FormField>
                <div className="flex gap-3">
                    <Button variant="primary" type="submit" disabled={form.processing}>
                        {t.common.save}
                    </Button>
                    <Link href="/tasks" className="px-4 py-2 text-sm text-zinc-700">
                        {t.common.cancel}
                    </Link>
                </div>
            </CardForm>
        </>
    );
}

Form.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
