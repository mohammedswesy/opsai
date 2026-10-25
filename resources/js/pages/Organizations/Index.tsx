import { Button } from '@/components/Button';
import { Card } from '@/components/Card';
import { Chip } from '@/components/Chip';
import { router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { FormPage } from '@/components/FormPage';
import { PageHeader } from '@/components/PageHeader';
import { useI18n } from '@/i18n';
import AppLayout from '@/layouts/AppLayout';
import { optionLabel } from '@/lib/labels';

type Membership = {
    id: string;
    name: string;
    slug: string;
    country: string;
    status: string;
    role: string;
    is_current?: boolean;
};

export default function OrganizationsIndex({ organizations }: { organizations: Membership[] }) {
    const { t } = useI18n();

    return (
        <FormPage>
            <PageHeader title={t.organizations.title} subtitle={t.organizations.subtitle} />
            <div className="grid gap-3">
                {organizations.map((organization) => (
                    <Card
                        className={`flex items-center justify-between gap-3 px-4 py-4 ${organization.is_current ? 'ring-2 ring-brand-border' : ''}`}
                        key={organization.id}
                        aria-current={organization.is_current ? 'true' : undefined}
                    >
                        <div className="min-w-0">
                            <h2 className="flex flex-wrap items-center gap-2 font-medium">
                                <bdi>{organization.name}</bdi>
                                {organization.is_current ? <Chip tone="brand">{t.organizations.current}</Chip> : null}
                            </h2>
                            <p className="text-body text-ink-muted">
                                {optionLabel(t.labels, organization.role)} · {organization.country} · {optionLabel(t.labels, organization.status)}
                            </p>
                        </div>
                        <Button variant="primary" type="button"
                            onClick={() => router.post('/organizations/switch', { organization_id: organization.id })}
                        >
                            {t.organizations.open}
                        </Button>
                    </Card>
                ))}
            </div>
        </FormPage>
    );
}

OrganizationsIndex.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
