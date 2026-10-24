import { router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { ButtonLink } from '@/components/Button';
import { Chip } from '@/components/Chip';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { DataTable, type Column } from '@/components/DataTable';
import { FilterBar, activeFilters } from '@/components/FilterBar';
import { controlClassName } from '@/components/FormField';
import { Initials } from '@/components/Initials';
import { PageHeader } from '@/components/PageHeader';
import { Pagination } from '@/components/Pagination';
import { RecordList } from '@/components/RecordList';
import { SearchInput } from '@/components/SearchInput';
import { StatusBadge } from '@/components/StatusBadge';
import { useI18n } from '@/i18n';
import AppLayout from '@/layouts/AppLayout';
import { optionLabel } from '@/lib/labels';
import { AVAILABILITY_STATUSES, RECORD_STATUSES } from '@/lib/options';
import type { Option, Paginator } from '@/types/page';

type TechnicianRow = {
    id: string;
    employee_code: string | null;
    availability_status: string;
    status: string;
    user: { name: string; email: string } | null;
    branch: { name: string } | null;
    skills: { skill: string }[];
};

type Filters = { search?: string; status?: string; availability_status?: string; branch_id?: string };

export default function Index({
    technicians,
    filters,
    branches,
    canManage,
}: {
    technicians: Paginator<TechnicianRow>;
    filters: Filters;
    branches: Option[];
    canManage: boolean;
}) {
    const { t } = useI18n();
    const [archiveId, setArchiveId] = useState<string | null>(null);
    const label = (value: string) => optionLabel(t.labels, value);
    const active = activeFilters(filters, {
        search: { label: t.common.search },
        availability_status: { label: t.ops.availability, value: label },
        status: { label: t.ops.status, value: label },
        branch_id: { label: t.ops.branch, value: (value) => branches.find((branch) => branch.id === value)?.name ?? value },
    });

    const columns: Column<TechnicianRow>[] = [
        {
            key: 'name',
            header: t.ops.technician,
            primary: true,
            cell: (row) => {
                const name = row.user?.name ?? t.common.none;

                return (
                    <span className="flex items-center gap-3">
                        <Initials name={name} />
                        <span className="min-w-0">
                            <span dir="auto" className="block max-w-56 truncate" title={name}>
                                {name}
                            </span>
                            {row.user?.email ? (
                                <bdi dir="ltr" className="block max-w-56 truncate text-caption font-normal text-ink-muted" title={row.user.email}>
                                    {row.user.email}
                                </bdi>
                            ) : null}
                        </span>
                    </span>
                );
            },
        },
        { key: 'code', header: t.ops.employeeCode, cell: (row) => (row.employee_code ? <bdi dir="ltr">{row.employee_code}</bdi> : t.common.none) },
        { key: 'branch', header: t.ops.branch, truncate: (row) => row.branch?.name, cell: (row) => row.branch?.name ?? t.common.none },
        {
            key: 'skills',
            header: t.ops.skills,
            cell: (row) =>
                row.skills.length === 0 ? (
                    <span className="text-ink-subtle">{t.common.none}</span>
                ) : (
                    <span className="flex flex-wrap gap-1">
                        {row.skills.map((skill) => (
                            <Chip key={skill.skill} tone="brand">
                                {label(skill.skill)}
                            </Chip>
                        ))}
                    </span>
                ),
        },
        { key: 'availability', header: t.ops.availability, cell: (row) => <StatusBadge value={row.availability_status} /> },
        { key: 'status', header: t.ops.status, cell: (row) => <StatusBadge value={row.status} /> },
    ];

    return (
        <>
            <PageHeader title={t.nav.technicians} action={canManage ? <ButtonLink href="/technicians/create">{t.ops.create}</ButtonLink> : null} />
            <FilterBar search={<SearchInput defaultValue={filters.search} placeholder={t.common.search} />} active={active}>
                <select className={controlClassName} name="availability_status" defaultValue={filters.availability_status ?? ''} aria-label={t.ops.availability}>
                    <option value="">{t.ops.availability}</option>
                    {AVAILABILITY_STATUSES.map((value) => (
                        <option key={value} value={value}>
                            {label(value)}
                        </option>
                    ))}
                </select>
                <select className={controlClassName} name="status" defaultValue={filters.status ?? ''} aria-label={t.ops.status}>
                    <option value="">{t.ops.status}</option>
                    {RECORD_STATUSES.map((value) => (
                        <option key={value} value={value}>
                            {label(value)}
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
            </FilterBar>
            <RecordList empty={technicians.data.length === 0} filtered={active.length > 0} loading="inline">
                <DataTable
                    caption={t.nav.technicians}
                    columns={columns}
                    rows={technicians.data}
                    rowKey={(row) => row.id}
                    actions={(row) => [
                        { key: 'view', label: t.common.view, href: `/technicians/${row.id}`, inline: true },
                        ...(canManage ? [{ key: 'archive', label: t.common.archive, tone: 'danger' as const, onClick: () => setArchiveId(row.id) }] : []),
                    ]}
                />
                <Pagination paginator={technicians} />
            </RecordList>
            <ConfirmDialog
                open={archiveId !== null}
                title={t.ops.archiveConfirm}
                confirmLabel={t.common.archive}
                cancelLabel={t.common.cancel}
                onCancel={() => setArchiveId(null)}
                onConfirm={() => {
                    if (archiveId) {
                        router.delete(`/technicians/${archiveId}`);
                    }
                    setArchiveId(null);
                }}
            />
        </>
    );
}

Index.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
