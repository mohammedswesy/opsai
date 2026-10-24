import { router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { Button, ButtonLink } from '@/components/Button';
import { Chip } from '@/components/Chip';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { DataTable } from '@/components/DataTable';
import { FilterBar } from '@/components/FilterBar';
import { controlClassName } from '@/components/FormField';
import { Initials } from '@/components/Initials';
import { PageHeader } from '@/components/PageHeader';
import { Pagination } from '@/components/Pagination';
import { RecordList } from '@/components/RecordList';
import { SearchInput } from '@/components/SearchInput';
import { StatusBadge } from '@/components/StatusBadge';
import { useI18n } from '@/i18n';
import { optionLabel } from '@/lib/labels';
import AppLayout from '@/layouts/AppLayout';
import { AVAILABILITY_STATUSES } from '@/lib/options';
import type { Option, Paginator } from '@/types/page';

type TechnicianRow = {
    id: string;
    employee_code: string | null;
    availability_status: string;
    status: string;
    user: { name: string } | null;
    branch: { name: string } | null;
    skills: { skill: string }[];
};

export default function Index({
    technicians,
    filters,
    branches,
    canManage,
}: {
    technicians: Paginator<TechnicianRow>;
    filters: { search?: string; status?: string; availability_status?: string; branch_id?: string };
    branches: Option[];
    canManage: boolean;
}) {
    const { t } = useI18n();
    const [archiveId, setArchiveId] = useState<string | null>(null);

    return (
        <>
            <PageHeader
                title={t.nav.technicians}
                action={canManage ? <ButtonLink href="/technicians/create">{t.ops.create}</ButtonLink> : null}
            />
            <FilterBar>
                <SearchInput defaultValue={filters.search} placeholder={t.common.search} />
                <select className={controlClassName} name="availability_status" defaultValue={filters.availability_status ?? ''} aria-label={t.ops.availability}>
                    <option value="">{t.ops.availability}</option>
                    {AVAILABILITY_STATUSES.map((value) => (
                        <option key={value} value={value}>
                            {optionLabel(t.labels, value)}
                        </option>
                    ))}
                </select>
                <select className={controlClassName} name="status" defaultValue={filters.status ?? ''} aria-label={t.ops.status}>
                    <option value="">{t.ops.status}</option>
                    <option value="active">{t.labels.active}</option>
                    <option value="inactive">{t.labels.inactive}</option>
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
            <RecordList empty={technicians.data.length === 0}>
                <DataTable headers={[t.ops.technician, t.ops.employeeCode, t.ops.branch, t.ops.skills, t.ops.availability, t.ops.status, '']}>
                    {technicians.data.map((technician) => {
                        const name = technician.user?.name ?? t.common.none;

                        return (
                            <tr key={technician.id}>
                                <td className="px-4 py-3">
                                    <div className="flex items-center gap-3">
                                        <Initials name={name} />
                                        <span className="font-medium text-zinc-900">{name}</span>
                                    </div>
                                </td>
                                <td className="px-4 py-3">{technician.employee_code ?? t.common.none}</td>
                                <td className="px-4 py-3">{technician.branch?.name ?? t.common.none}</td>
                                <td className="px-4 py-3">
                                    <div className="flex flex-wrap gap-1">
                                        {technician.skills.length === 0 ? (
                                            <span className="text-zinc-400">{t.common.none}</span>
                                        ) : (
                                            technician.skills.map((skill) => (
                                                <Chip key={skill.skill} tone="brand">
                                                    {optionLabel(t.labels, skill.skill)}
                                                </Chip>
                                            ))
                                        )}
                                    </div>
                                </td>
                                <td className="px-4 py-3">
                                    <StatusBadge value={technician.availability_status} />
                                </td>
                                <td className="px-4 py-3">
                                    <StatusBadge value={technician.status} />
                                </td>
                                <td className="px-4 py-3 text-end">
                                    <div className="flex justify-end gap-1">
                                        <ButtonLink href={`/technicians/${technician.id}`} variant="ghost">
                                            {t.common.view}
                                        </ButtonLink>
                                        {canManage ? (
                                            <Button variant="ghost" onClick={() => setArchiveId(technician.id)}>
                                                {t.common.archive}
                                            </Button>
                                        ) : null}
                                    </div>
                                </td>
                            </tr>
                        );
                    })}
                </DataTable>
            </RecordList>
            <Pagination links={technicians.links} />
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
