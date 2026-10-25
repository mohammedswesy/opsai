import { Button } from '@/components/Button';
import { Card, CardForm } from '@/components/Card';
import { useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { FormField, Input, Select } from '@/components/FormField';
import { FormPage } from '@/components/FormPage';
import { PageHeader } from '@/components/PageHeader';
import { useI18n } from '@/i18n';
import AppLayout from '@/layouts/AppLayout';

type IntegrationView = {
    provider: string;
    status: string;
    phone_number_id: string | null;
    display_phone: string | null;
    business_account_id: string | null;
    token_hint: string | null;
    secret_hint: string | null;
    verify_token_set: boolean;
};

export default function Settings({ integration }: { integration: IntegrationView | null; status: string }) {
    const { t } = useI18n();
    const form = useForm({
        phone_number_id: integration?.phone_number_id ?? '',
        display_phone: integration?.display_phone ?? '',
        business_account_id: integration?.business_account_id ?? '',
        access_token: '',
        app_secret: '',
        verify_token: '',
        status: integration?.status ?? 'disabled',
    });

    return (
        <FormPage>
            <PageHeader parent={{ label: t.nav.whatsapp, href: '/whatsapp' }} title={t.whatsapp.settings} />
            <CardForm className="space-y-4 p-5" onSubmit={(event) => {
                    event.preventDefault();
                    form.put('/settings/integrations/whatsapp');
                }}
            >
                <FormField label={t.whatsapp.phoneNumberId} error={form.errors.phone_number_id}>
                    <Input value={form.data.phone_number_id} onChange={(event) => form.setData('phone_number_id', event.target.value)} />
                </FormField>
                <FormField label={t.whatsapp.displayPhone} error={form.errors.display_phone}>
                    <Input value={form.data.display_phone} onChange={(event) => form.setData('display_phone', event.target.value)} />
                </FormField>
                <FormField label={t.whatsapp.businessAccount} error={form.errors.business_account_id}>
                    <Input value={form.data.business_account_id} onChange={(event) => form.setData('business_account_id', event.target.value)} />
                </FormField>
                <FormField label={t.whatsapp.accessToken} error={form.errors.access_token}>
                    <Input type="password" autoComplete="off" value={form.data.access_token} onChange={(event) => form.setData('access_token', event.target.value)} placeholder={integration?.token_hint ?? ''} />
                </FormField>
                <FormField label={t.whatsapp.appSecret} error={form.errors.app_secret}>
                    <Input type="password" autoComplete="off" value={form.data.app_secret} onChange={(event) => form.setData('app_secret', event.target.value)} placeholder={integration?.secret_hint ?? ''} />
                </FormField>
                <FormField label={t.whatsapp.verifyToken} error={form.errors.verify_token}>
                    <Input type="password" autoComplete="off" value={form.data.verify_token} onChange={(event) => form.setData('verify_token', event.target.value)} placeholder={integration?.verify_token_set ? t.whatsapp.tokenHint : ''} />
                </FormField>
                <FormField label={t.whatsapp.status} error={form.errors.status}>
                    <Select value={form.data.status} onChange={(event) => form.setData('status', event.target.value)}>
                        <option value="active">{t.whatsapp.connected}</option>
                        <option value="disabled">{t.whatsapp.disabled}</option>
                    </Select>
                </FormField>
                <div className="flex gap-3">
                    <Button variant="primary" type="submit">{t.whatsapp.save}</Button>
                    <Button variant="secondary" type="button" onClick={() => form.post('/settings/integrations/whatsapp/verify')}>{t.whatsapp.verify}</Button>
                </div>
            </CardForm>
        </FormPage>
    );
}

Settings.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
