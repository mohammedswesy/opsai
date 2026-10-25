import { router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { ButtonLink } from '@/components/Button';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { DataTable, type Column } from '@/components/DataTable';
import { PageHeader } from '@/components/PageHeader';
import { RecordList } from '@/components/RecordList';
import { StatusBadge } from '@/components/StatusBadge';
import { useI18n } from '@/i18n';
import AppLayout from '@/layouts/AppLayout';

type BranchRow = {
    id: string;
    name: string;
    code: string;
    city: string | null;
    country: string;
    phone: string | null;
    timezone: string;
    status: string;
};

export default function BranchesIndex({ branches, canManage }: { branches: BranchRow[]; canManage: boolean }) {
    const { t } = useI18n();
    const [pendingId, setPendingId] = useState<string | null>(null);

    const columns: Column<BranchRow>[] = [
        { key: 'name', header: t.branches.name, primary: true, truncate: (row) => row.name, cell: (row) => row.name },
        { key: 'code', header: t.branches.code, cell: (row) => <bdi dir="ltr">{row.code}</bdi> },
        { key: 'city', header: t.branches.city, truncate: (row) => row.city, cell: (row) => row.city ?? t.common.none },
        { key: 'phone', header: t.branches.phone, cell: (row) => (row.phone ? <bdi dir="ltr" className="tabular-nums">{row.phone}</bdi> : <span className="text-ink-subtle">{t.common.none}</span>) },
        { key: 'timezone', header: t.branches.timezone, hideOnMobile: true, cell: (row) => <bdi dir="ltr">{row.timezone}</bdi> },
        { key: 'status', header: t.branches.status, cell: (row) => <StatusBadge value={row.status} /> },
    ];

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
            <RecordList empty={branches.length === 0} emptyTitle={t.branches.empty} loading="inline">
                <DataTable
                    caption={t.branches.title}
                    columns={columns}
                    rows={branches}
                    rowKey={(row) => row.id}
                    actions={
                        canManage
                            ? (row) => [
                                  { key: 'edit', label: t.common.edit, href: `/branches/${row.id}/edit`, inline: true },
                                  { key: 'archive', label: t.branches.archive, tone: 'danger', onClick: () => setPendingId(row.id) },
                              ]
                            : undefined
                    }
                />
            </RecordList>
            <ConfirmDialog
                tone="danger"
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
