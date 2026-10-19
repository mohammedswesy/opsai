import { Card } from '@/components/Card';
import { Button, ButtonLink } from '@/components/Button';
import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { PageHeader } from '@/components/PageHeader';
import { StatusBadge } from '@/components/StatusBadge';
import { useI18n } from '@/i18n';
import { optionLabel } from '@/lib/labels';
import AppLayout from '@/layouts/AppLayout';

export default function Show({
    technician,
    canManage,
}: {
    technician: {
        id: string;
        employee_code: string | null;
        availability_status: string;
        hourly_rate: string | null;
        status: string;
        user: { name: string; email: string } | null;
        branch: { name: string } | null;
        skills: { skill: string; service: { name: string } | null }[];
    };
    canManage: boolean;
}) {
    const { t } = useI18n();

    return (
        <>
            <PageHeader
                title={technician.user?.name ?? t.ops.technician}
                action={
                    canManage ? (
                        <ButtonLink variant="primary" href={`/technicians/${technician.id}/edit`}>
                            {t.common.edit}
                        </ButtonLink>
                    ) : null
                }
            />
            <Card className="grid gap-3 p-5 sm:grid-cols-2">
                <p>{technician.user?.email}</p>
                <p>{technician.employee_code ?? t.common.none}</p>
                <p>{technician.branch?.name ?? t.common.none}</p>
                <p>{technician.hourly_rate ?? t.common.none}</p>
                <StatusBadge value={technician.availability_status} />
                <StatusBadge value={technician.status} />
                <p className="sm:col-span-2">{technician.skills.map((skill) => optionLabel(t.labels, skill.skill)).join(', ')}</p>
            </Card>
        </>
    );
}

Show.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
