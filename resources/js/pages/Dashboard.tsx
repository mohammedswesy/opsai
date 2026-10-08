import type { ReactNode } from 'react';
import { PageHeader } from '@/components/PageHeader';
import { useI18n } from '@/i18n';
import AppLayout from '@/layouts/AppLayout';

export default function Dashboard({ stats }: { stats: { branches: number; members: number } }) {
    const { t } = useI18n();

    return (
        <>
            <PageHeader title={t.dashboard.title} subtitle={t.dashboard.subtitle} />
            <div className="grid gap-4 sm:grid-cols-2">
                <article className="rounded-lg border border-zinc-200 bg-white p-5">
                    <p className="text-sm text-zinc-500">{t.dashboard.branches}</p>
                    <p className="mt-2 text-3xl font-semibold">{stats.branches}</p>
                </article>
                <article className="rounded-lg border border-zinc-200 bg-white p-5">
                    <p className="text-sm text-zinc-500">{t.dashboard.members}</p>
                    <p className="mt-2 text-3xl font-semibold">{stats.members}</p>
                </article>
            </div>
        </>
    );
}

Dashboard.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
