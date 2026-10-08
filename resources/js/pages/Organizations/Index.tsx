import { router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { PageHeader } from '@/components/PageHeader';
import { useI18n } from '@/i18n';
import AppLayout from '@/layouts/AppLayout';

type Membership = {
    id: string;
    name: string;
    slug: string;
    country: string;
    status: string;
    role: string;
};

export default function OrganizationsIndex({ organizations }: { organizations: Membership[] }) {
    const { t } = useI18n();

    return (
        <>
            <PageHeader title={t.organizations.title} subtitle={t.organizations.subtitle} />
            <div className="grid gap-3">
                {organizations.map((organization) => (
                    <article key={organization.id} className="flex items-center justify-between rounded-lg border border-zinc-200 bg-white px-4 py-4">
                        <div>
                            <h2 className="font-medium">{organization.name}</h2>
                            <p className="text-sm text-zinc-500">
                                {organization.role} · {organization.country} · {organization.status}
                            </p>
                        </div>
                        <button
                            type="button"
                            className="rounded-md bg-zinc-900 px-3 py-2 text-sm text-white"
                            onClick={() => router.post('/organizations/switch', { organization_id: organization.id })}
                        >
                            {t.organizations.open}
                        </button>
                    </article>
                ))}
            </div>
        </>
    );
}

OrganizationsIndex.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
