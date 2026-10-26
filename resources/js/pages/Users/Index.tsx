import { Button } from '@/components/Button';
import { CardForm } from '@/components/Card';
import { router, useForm } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { Chip } from '@/components/Chip';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { controlClassName, FormField } from '@/components/FormField';
import { DataTable, type Column } from '@/components/DataTable';
import { Initials } from '@/components/Initials';
import { RecordList } from '@/components/RecordList';
import { FormPage } from '@/components/FormPage';
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

    const branchNames = new Map(branches.map((branch) => [branch.id, branch.name]));
    const columns: Column<Member>[] = [
        {
            key: 'name',
            header: t.users.name,
            primary: true,
            cell: (row) => (
                <span className="flex items-center gap-3">
                    <Initials name={row.name} />
                    <span dir="auto" className="block max-w-56 truncate" title={row.name}>
                        {row.name}
                    </span>
                </span>
            ),
        },
        {
            key: 'email',
            header: t.users.email,
            cell: (row) => (
                <bdi dir="ltr" className="inline-block max-w-64 truncate align-middle" title={row.email}>
                    {row.email}
                </bdi>
            ),
        },
        { key: 'role', header: t.users.role, cell: (row) => optionLabel(t.labels, row.role) },
        {
            key: 'branches',
            header: t.users.branches,
            cell: (row) => {
                const names = row.branch_ids.map((id) => branchNames.get(id)).filter((name): name is string => Boolean(name));

                return names.length === 0 ? (
                    <span className="text-ink-muted">{t.common.none}</span>
                ) : (
                    <span className="flex flex-wrap gap-1">
                        {names.map((name) => (
                            <Chip key={name}>
                                <bdi>{name}</bdi>
                            </Chip>
                        ))}
                    </span>
                );
            },
        },
    ];

    return (
        <>
            <PageHeader title={t.users.title} subtitle={t.users.subtitle} />
            {canManage ? (
                <FormPage size="wide" className="mb-6">
                    <CardForm className="grid gap-4 p-5 md:grid-cols-2" onSubmit={(event) => {
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
                </FormPage>
            ) : null}
            <RecordList empty={members.length === 0} emptyTitle={t.users.empty} loading="inline">
                <DataTable
                    caption={t.users.title}
                    columns={columns}
                    rows={members}
                    rowKey={(row) => row.id}
                    actions={canManage ? (row) => [{ key: 'remove', label: t.users.remove, tone: 'danger', onClick: () => setPendingId(row.id) }] : undefined}
                />
            </RecordList>
            <ConfirmDialog
                tone="danger"
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
