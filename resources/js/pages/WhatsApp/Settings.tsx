import { Button } from '@/components/Button';
import { CardForm } from '@/components/Card';
import { router, useForm, usePage } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';
import { Badge, type BadgeTone } from '@/components/Badge';
import { Checkbox, FormField, Input, Select } from '@/components/FormField';
import { FormPage } from '@/components/FormPage';
import { PageHeader } from '@/components/PageHeader';
import { Section } from '@/components/Section';
import { WhatsAppAttention, type WhatsAppHealth } from '@/components/WhatsAppAttention';
import { useI18n } from '@/i18n';
import AppLayout from '@/layouts/AppLayout';
import { useFormat } from '@/lib/format';

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

type TemplateRow = {
    id: string;
    name: string;
    language: string;
    purpose: string;
    status: string;
    parameters: string[];
    synced_at: string | null;
};

const statusTones: Record<string, BadgeTone> = { approved: 'success', pending: 'warning', rejected: 'danger', unknown: 'muted' };

export default function Settings({
    integration,
    health,
    remindersEnabled,
    templates,
}: {
    integration: IntegrationView | null;
    status: string;
    health: WhatsAppHealth;
    remindersEnabled: boolean;
    templates: TemplateRow[];
}) {
    const { t, locale } = useI18n();
    const format = useFormat();
    const { errors } = usePage().props as { errors: Record<string, string | undefined> };
    const [syncing, setSyncing] = useState(false);
    // Moves at once and falls back to the saved value if the request fails.
    const [reminders, setReminders] = useState(remindersEnabled);
    useEffect(() => setReminders(remindersEnabled), [remindersEnabled]);
    const form = useForm({
        phone_number_id: integration?.phone_number_id ?? '',
        display_phone: integration?.display_phone ?? '',
        business_account_id: integration?.business_account_id ?? '',
        access_token: '',
        app_secret: '',
        verify_token: '',
        status: integration?.status ?? 'disabled',
    });
    const copy = t.whatsapp.templates;
    const reminderApproved = templates.some((template) => template.purpose === 'appointment_reminder' && template.status === 'approved');

    return (
        <FormPage>
            <PageHeader parent={{ label: t.nav.whatsapp, href: '/whatsapp' }} title={t.whatsapp.settings} />
            <WhatsAppAttention health={health} canManageSettings showSettingsLink={false} />
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

            <Section className="mt-4" title={t.whatsapp.reminders.title} description={t.whatsapp.reminders.description}>
                <Checkbox
                    data-testid="whatsapp-reminders-toggle"
                    role="switch"
                    aria-checked={reminders}
                    label={t.whatsapp.reminders.enable}
                    checked={reminders}
                    disabled={integration === null}
                    onChange={(event) => {
                        const enabled = event.target.checked;
                        setReminders(enabled);
                        router.put('/settings/integrations/whatsapp/reminders', { enabled }, { preserveScroll: true, onError: () => setReminders(remindersEnabled) });
                    }}
                />
                {reminders && !reminderApproved ? <p className="mt-2 text-caption text-warning">{t.whatsapp.reminders.needsTemplate}</p> : null}
            </Section>

            <Section
                className="mt-4"
                title={copy.title}
                description={copy.description}
                flush
                action={
                    <Button
                        variant="secondary"
                        type="button"
                        disabled={integration === null || syncing}
                        onClick={() => router.post('/settings/integrations/whatsapp/templates/sync', {}, { preserveScroll: true, onStart: () => setSyncing(true), onFinish: () => setSyncing(false) })}
                    >
                        <RefreshCw className={`size-4 ${syncing ? 'animate-spin' : ''}`} strokeWidth={1.75} aria-hidden="true" />
                        {copy.sync}
                    </Button>
                }
            >
                {errors.templates ? (
                    <p className="mx-5 mb-3 text-body text-danger" role="alert">
                        {t.whatsapp.errors[errors.templates] ?? copy.syncFailed}
                    </p>
                ) : null}
                {templates.length === 0 ? (
                    <p className="px-5 pb-5 text-body text-ink-muted">{copy.empty}</p>
                ) : (
                    <ul data-testid="whatsapp-templates" className="divide-y divide-line border-t border-line">
                        {templates.map((template) => (
                            <li key={template.id} data-testid="whatsapp-template" className="flex flex-wrap items-start justify-between gap-x-4 gap-y-2 px-5 py-3">
                                <div className="min-w-0 space-y-0.5">
                                    <p className="font-medium text-ink">
                                        {copy.purposes[template.purpose] ?? template.purpose}
                                        <span className="text-ink-muted"> · {copy.languages[template.language] ?? template.language}</span>
                                    </p>
                                    <p className="text-caption text-ink-muted">
                                        <bdi dir="ltr" className="font-mono">{template.name}</bdi>
                                    </p>
                                    <p className="text-caption text-ink-secondary">
                                        {copy.parameters}:{' '}
                                        {template.parameters.map((field, index) => `{{${index + 1}}} ${copy.fields[field] ?? field}`).join(locale === 'ar' ? '، ' : ', ')}
                                    </p>
                                    <p className="text-caption text-ink-muted tabular-nums">
                                        {copy.lastSynced}: {template.synced_at ? format.dateTime(template.synced_at) : copy.neverSynced}
                                    </p>
                                </div>
                                <Badge tone={statusTones[template.status] ?? 'muted'}>{copy.statuses[template.status] ?? template.status}</Badge>
                            </li>
                        ))}
                    </ul>
                )}
            </Section>
        </FormPage>
    );
}

Settings.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
