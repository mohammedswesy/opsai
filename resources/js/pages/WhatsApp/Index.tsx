import { Avatar } from '@/components/Avatar';
import { Card, CardForm } from '@/components/Card';
import { Input } from '@/components/FormField';
import { Button, ButtonLink } from '@/components/Button';
import { Link, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { EmptyState } from '@/components/EmptyState';
import { PageHeader } from '@/components/PageHeader';
import { Pagination } from '@/components/Pagination';
import { StatusBadge } from '@/components/StatusBadge';
import { useI18n } from '@/i18n';
import AppLayout from '@/layouts/AppLayout';
import { useFormat } from '@/lib/format';
import type { Paginator } from '@/types/page';

type ConversationRow = {
    id: string;
    customer: string | null;
    phone: string;
    status: string;
    unread_count: number;
    last_message_at: string | null;
    preview: string | null;
};

export default function Index({ conversations, simulation }: { conversations: Paginator<ConversationRow>; simulation: boolean }) {
    const { t } = useI18n();
    const format = useFormat();
    const form = useForm({ from: '', text: '' });

    return (
        <>
            <PageHeader title={t.whatsapp.title} subtitle={t.whatsapp.emptyHint} action={<ButtonLink variant="secondary" href="/settings/integrations/whatsapp">{t.whatsapp.settings}</ButtonLink>} />
            {simulation ? (
                <CardForm className="mb-4 grid items-end gap-3 p-4 md:grid-cols-[1fr_2fr_auto]" onSubmit={(event) => {
                        event.preventDefault();
                        form.post('/whatsapp/simulate');
                    }}
                >
                    <Input aria-label={t.whatsapp.phone} value={form.data.from} onChange={(event) => form.setData('from', event.target.value)} />
                    <Input aria-label={t.whatsapp.message} value={form.data.text} onChange={(event) => form.setData('text', event.target.value)} />
                    <Button variant="primary" type="submit">{t.whatsapp.simulate}</Button>
                </CardForm>
            ) : null}
            {conversations.data.length === 0 ? <EmptyState title={t.whatsapp.empty} description={t.whatsapp.emptyHint} /> : null}
            {conversations.data.length > 0 ? (
                <Card className="overflow-hidden">
                    {conversations.data.map((conversation) => (
                        <Link key={conversation.id} href={`/whatsapp/${conversation.id}`} className="flex items-start gap-3 border-b border-line px-4 py-3 transition-colors last:border-b-0 hover:bg-surface-muted">
                            <Avatar name={conversation.customer ?? conversation.phone} />
                            <span className="min-w-0 flex-1">
                                <span className="flex items-center justify-between gap-3">
                                    <span className="truncate font-medium text-ink">{conversation.customer ?? t.whatsapp.unknown}</span>
                                    <span className="flex shrink-0 items-center gap-2">
                                        {conversation.last_message_at ? <span className="text-caption text-ink-muted tabular-nums">{format.sameDay(conversation.last_message_at, new Date()) ? format.time(conversation.last_message_at) : format.date(new Date(conversation.last_message_at), 'short')}</span> : null}
                                        {conversation.unread_count > 0 ? <span className="rounded-badge bg-brand px-2 py-0.5 text-caption font-semibold text-on-brand tabular-nums">{conversation.unread_count}</span> : null}
                                    </span>
                                </span>
                                <span className="mt-0.5 block truncate text-body text-ink-secondary" title={conversation.preview ?? ''}>{conversation.preview}</span>
                                <span className="mt-2 block"><StatusBadge value={conversation.status} /></span>
                            </span>
                        </Link>
                    ))}
                </Card>
            ) : null}
            <Pagination paginator={conversations} />
        </>
    );
}

Index.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
