import type { ReactNode } from 'react';
import { ButtonLink } from '@/components/Button';
import { Chip } from '@/components/Chip';
import { DescriptionItem, DescriptionList } from '@/components/DescriptionList';
import { RecordHeader, type RecordMeta } from '@/components/RecordHeader';
import { Section } from '@/components/Section';
import { StatusBadge } from '@/components/StatusBadge';
import { ExternalTextLink } from '@/components/TextLink';
import { useI18n } from '@/i18n';
import AppLayout from '@/layouts/AppLayout';
import { useFormat } from '@/lib/format';
import { optionLabel } from '@/lib/labels';

type Technician = {
    id: string;
    employee_code: string | null;
    availability_status: string;
    hourly_rate: string | null;
    status: string;
    created_at: string | null;
    user: { name: string; email: string } | null;
    branch: { name: string } | null;
    skills: { id?: string; skill: string; service: { name: string } | null }[];
};

export default function Show({ technician, canManage }: { technician: Technician; canManage: boolean }) {
    const { t } = useI18n();
    const format = useFormat();
    const meta: RecordMeta[] = [
        { key: 'branch', label: t.ops.branch, value: technician.branch ? <bdi>{technician.branch.name}</bdi> : t.common.none },
        { key: 'code', label: t.ops.employeeCode, value: technician.employee_code ? <bdi dir="ltr">{technician.employee_code}</bdi> : t.common.none },
    ];

    return (
        <>
            <RecordHeader
                eyebrow={t.ops.technician}
                title={technician.user?.name ?? t.ops.technician}
                badges={
                    <>
                        <StatusBadge value={technician.availability_status} />
                        <StatusBadge value={technician.status} />
                    </>
                }
                meta={meta}
                actions={
                    canManage ? (
                        <ButtonLink variant="primary" href={`/technicians/${technician.id}/edit`}>
                            {t.common.edit}
                        </ButtonLink>
                    ) : null
                }
            />

            <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <Section title={t.detail.profile}>
                    <DescriptionList>
                        <DescriptionItem label={t.detail.email} ltr>
                            {technician.user?.email ? <ExternalTextLink href={`mailto:${technician.user.email}`}>{technician.user.email}</ExternalTextLink> : null}
                        </DescriptionItem>
                        <DescriptionItem label={t.ops.employeeCode} ltr>
                            {technician.employee_code}
                        </DescriptionItem>
                        <DescriptionItem label={t.ops.branch} isolate>
                            {technician.branch?.name}
                        </DescriptionItem>
                        <DescriptionItem label={t.ops.hourlyRate}>
                            {technician.hourly_rate !== null ? <span className="tabular-nums">{format.money(technician.hourly_rate)}</span> : null}
                        </DescriptionItem>
                        <DescriptionItem label={t.ops.availability}>
                            <StatusBadge value={technician.availability_status} />
                        </DescriptionItem>
                        <DescriptionItem label={t.ops.status}>
                            <StatusBadge value={technician.status} />
                        </DescriptionItem>
                        <DescriptionItem label={t.detail.created}>{technician.created_at ? format.date(new Date(technician.created_at)) : null}</DescriptionItem>
                    </DescriptionList>
                </Section>

                <aside className="min-w-0 space-y-5">
                    <Section title={t.ops.skills}>
                        {technician.skills.length === 0 ? (
                            <p className="text-body text-ink-muted">{t.common.none}</p>
                        ) : (
                            <ul className="divide-y divide-line">
                                {technician.skills.map((skill, index) => (
                                    <li key={skill.id ?? `${skill.skill}-${index}`} className="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0">
                                        <Chip tone="brand">{optionLabel(t.labels, skill.skill)}</Chip>
                                        <span className="min-w-0 truncate text-caption text-ink-muted" title={skill.service?.name}>
                                            {skill.service ? (
                                                <>
                                                    <span className="sr-only">{t.detail.linkedService}: </span>
                                                    <bdi>{skill.service.name}</bdi>
                                                </>
                                            ) : null}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Section>
                </aside>
            </div>
        </>
    );
}

Show.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
