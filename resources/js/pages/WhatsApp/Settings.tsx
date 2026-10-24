import { Button } from '@/components/Button';
import { Card, CardForm } from '@/components/Card';
import { useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';
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
        <>
            <PageHeader parent={{ label: t.nav.whatsapp, href: '/whatsapp' }} title={t.whatsapp.settings} />
            <CardForm className="max-w-xl space-y-4 p-4" onSubmit={(event) => {
                    event.preventDefault();
                    form.put('/settings/integrations/whatsapp');
                }}
            >
                <label className="block text-sm">
                    {t.whatsapp.phoneNumberId}
                    <input className="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2" value={form.data.phone_number_id} onChange={(event) => form.setData('phone_number_id', event.target.value)} />
                </label>
                <label className="block text-sm">
                    {t.whatsapp.displayPhone}
                    <input className="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2" value={form.data.display_phone} onChange={(event) => form.setData('display_phone', event.target.value)} />
                </label>
                <label className="block text-sm">
                    {t.whatsapp.businessAccount}
                    <input className="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2" value={form.data.business_account_id} onChange={(event) => form.setData('business_account_id', event.target.value)} />
                </label>
                <label className="block text-sm">
                    {t.whatsapp.accessToken}
                    <input className="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2" type="password" autoComplete="off" value={form.data.access_token} onChange={(event) => form.setData('access_token', event.target.value)} placeholder={integration?.token_hint ?? ''} />
                </label>
                <label className="block text-sm">
                    {t.whatsapp.appSecret}
                    <input className="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2" type="password" autoComplete="off" value={form.data.app_secret} onChange={(event) => form.setData('app_secret', event.target.value)} placeholder={integration?.secret_hint ?? ''} />
                </label>
                <label className="block text-sm">
                    {t.whatsapp.verifyToken}
                    <input className="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2" type="password" autoComplete="off" value={form.data.verify_token} onChange={(event) => form.setData('verify_token', event.target.value)} placeholder={integration?.verify_token_set ? t.whatsapp.tokenHint : ''} />
                </label>
                <label className="block text-sm">
                    {t.whatsapp.status}
                    <select className="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2" value={form.data.status} onChange={(event) => form.setData('status', event.target.value)}>
                        <option value="active">{t.whatsapp.connected}</option>
                        <option value="disabled">{t.whatsapp.disabled}</option>
                    </select>
                </label>
                <div className="flex gap-3">
                    <Button variant="primary" type="submit">{t.whatsapp.save}</Button>
                    <Button variant="secondary" type="button" onClick={() => form.post('/settings/integrations/whatsapp/verify')}>{t.whatsapp.verify}</Button>
                </div>
            </CardForm>
        </>
    );
}

Settings.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
