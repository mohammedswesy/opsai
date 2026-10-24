import { Button } from '@/components/Button';
import { Card, CardForm } from '@/components/Card';
import { router, useForm } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { controlClassName, FormField } from '@/components/FormField';
import { EmptyState } from '@/components/EmptyState';
import { PageHeader } from '@/components/PageHeader';
import { useI18n } from '@/i18n';
import AppLayout from '@/layouts/AppLayout';
import { optionLabel } from '@/lib/labels';

type Member = {
    id: string;
    name: string;
    email: string;
    role: string;
    branch_ids: string[];
};

type BranchOption = { id: string; name: string };

export default function UsersIndex({
    members,
    branches,
    canManage,
    assignableRoles,
}: {
    members: Member[];
    branches: BranchOption[];
    canManage: boolean;
    assignableRoles: string[];
}) {
    const { t } = useI18n();
    const [pendingId, setPendingId] = useState<string | null>(null);
    const form = useForm({
        name: '',
        email: '',
        password: '',
        role: assignableRoles[0] ?? 'technician',
        branch_ids: [] as string[],
    });

    return (
        <>
            <PageHeader title={t.users.title} subtitle={t.users.subtitle} />
            {canManage ? (
                <CardForm className="mb-6 grid gap-4 p-5 md:grid-cols-2" onSubmit={(event) => {
                        event.preventDefault();
                        form.post('/users', { onSuccess: () => form.reset() });
                    }}
                >
                    <FormField label={t.users.name} error={form.errors.name}>
                        <input className={controlClassName} value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />
                    </FormField>
                    <FormField label={t.users.email} error={form.errors.email}>
                        <input className={controlClassName} type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} />
                    </FormField>
                    <FormField label={t.users.password} error={form.errors.password}>
                        <input className={controlClassName} type="password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} />
                    </FormField>
                    <FormField label={t.users.role} error={form.errors.role}>
                        <select className={controlClassName} value={form.data.role} onChange={(event) => form.setData('role', event.target.value)}>
                            {assignableRoles.map((role) => (
                                <option key={role} value={role}>
                                    {optionLabel(t.labels, role)}
                                </option>
                            ))}
                        </select>
                    </FormField>
                    <FormField label={t.users.branches}>
                        <select
                            multiple
                            className={controlClassName}
                            value={form.data.branch_ids}
                            onChange={(event) =>
                                form.setData(
                                    'branch_ids',
                                    Array.from(event.target.selectedOptions).map((option) => option.value),
                                )
                            }
                        >
                            {branches.map((branch) => (
                                <option key={branch.id} value={branch.id}>
                                    {branch.name}
                                </option>
                            ))}
                        </select>
                    </FormField>
                    <div className="md:col-span-2">
                        <Button variant="primary" type="submit" disabled={form.processing}>
                            {t.users.add}
                        </Button>
                    </div>
                </CardForm>
            ) : null}
            {members.length === 0 ? (
                <EmptyState title={t.users.empty} />
            ) : (
                <Card className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead className="bg-zinc-50 text-zinc-500">
                            <tr>
                                <th className="px-4 py-3 text-start font-medium">{t.users.name}</th>
                                <th className="px-4 py-3 text-start font-medium">{t.users.email}</th>
                                <th className="px-4 py-3 text-start font-medium">{t.users.role}</th>
                                {canManage ? <th className="px-4 py-3" /> : null}
                            </tr>
                        </thead>
                        <tbody>
                            {members.map((member) => (
                                <tr key={member.id} className="border-t border-zinc-100">
                                    <td className="px-4 py-3">{member.name}</td>
                                    <td className="px-4 py-3">{member.email}</td>
                                    <td className="px-4 py-3">{optionLabel(t.labels, member.role)}</td>
                                    {canManage ? (
                                        <td className="px-4 py-3 text-end">
                                            <button type="button" className="text-red-700" onClick={() => setPendingId(member.id)}>
                                                {t.users.remove}
                                            </button>
                                        </td>
                                    ) : null}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </Card>
            )}
            <ConfirmDialog
                open={pendingId !== null}
                title={t.users.removeConfirm}
                confirmLabel={t.common.confirm}
                cancelLabel={t.common.cancel}
                onCancel={() => setPendingId(null)}
                onConfirm={() => {
                    if (pendingId) {
                        router.delete(`/users/${pendingId}`);
                    }
                    setPendingId(null);
                }}
            />
        </>
    );
}

UsersIndex.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
