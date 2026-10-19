import { Card } from '@/components/Card';
import { Button, ButtonLink } from '@/components/Button';
import { Link, router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { EmptyState } from '@/components/EmptyState';
import { PageHeader } from '@/components/PageHeader';
import { StatusBadge } from '@/components/StatusBadge';
import { useI18n } from '@/i18n';
import AppLayout from '@/layouts/AppLayout';

type BranchRow = {
    id: string;
    name: string;
    code: string;
    city: string | null;
    country: string;
    status: string;
};

export default function BranchesIndex({ branches, canManage }: { branches: BranchRow[]; canManage: boolean }) {
    const { t } = useI18n();
    const [pendingId, setPendingId] = useState<string | null>(null);

    return (
        <>
            <PageHeader
                title={t.branches.title}
                subtitle={t.branches.subtitle}
                action={
                    canManage ? (
                        <ButtonLink variant="primary" href="/branches/create">
                            {t.branches.create}
                        </ButtonLink>
                    ) : null
                }
            />
            {branches.length === 0 ? (
                <EmptyState title={t.branches.empty} />
            ) : (
                <Card className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead className="bg-zinc-50 text-start text-zinc-500">
                            <tr>
                                <th className="px-4 py-3 text-start font-medium">{t.branches.name}</th>
                                <th className="px-4 py-3 text-start font-medium">{t.branches.code}</th>
                                <th className="px-4 py-3 text-start font-medium">{t.branches.city}</th>
                                <th className="px-4 py-3 text-start font-medium">{t.branches.status}</th>
                                {canManage ? <th className="px-4 py-3" /> : null}
                            </tr>
                        </thead>
                        <tbody>
                            {branches.map((branch) => (
                                <tr key={branch.id} className="border-t border-zinc-100">
                                    <td className="px-4 py-3">{branch.name}</td>
                                    <td className="px-4 py-3">{branch.code}</td>
                                    <td className="px-4 py-3">{branch.city}</td>
                                    <td className="px-4 py-3">
                                        <StatusBadge value={branch.status} />
                                    </td>
                                    {canManage ? (
                                        <td className="px-4 py-3 text-end">
                                            <Link href={`/branches/${branch.id}/edit`} className="text-teal-800">
                                                {t.branches.edit}
                                            </Link>
                                            <button type="button" className="ms-3 text-red-700" onClick={() => setPendingId(branch.id)}>
                                                {t.branches.archive}
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
                title={t.branches.archiveConfirm}
                confirmLabel={t.common.confirm}
                cancelLabel={t.common.cancel}
                onCancel={() => setPendingId(null)}
                onConfirm={() => {
                    if (pendingId) {
                        router.delete(`/branches/${pendingId}`);
                    }
                    setPendingId(null);
                }}
            />
        </>
    );
}

BranchesIndex.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
