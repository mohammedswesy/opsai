import { Button } from '@/components/Button';
import { Card } from '@/components/Card';
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
};

export default function OrganizationsIndex({ organizations }: { organizations: Membership[] }) {
    const { t } = useI18n();

    return (
        <FormPage>
            <PageHeader title={t.organizations.title} subtitle={t.organizations.subtitle} />
            <div className="grid gap-3">
                {organizations.map((organization) => (
                    <Card className="flex items-center justify-between px-4 py-4" key={organization.id}>
                        <div>
                            <h2 className="font-medium">{organization.name}</h2>
                            <p className="text-sm text-zinc-500">
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
