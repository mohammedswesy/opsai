import { Card, CardForm } from '@/components/Card';
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
                    <input className="min-h-11 rounded-lg border border-zinc-200 px-3 text-sm" aria-label={t.whatsapp.phone} value={form.data.from} onChange={(event) => form.setData('from', event.target.value)} />
                    <input className="min-h-11 rounded-lg border border-zinc-200 px-3 text-sm" aria-label={t.whatsapp.message} value={form.data.text} onChange={(event) => form.setData('text', event.target.value)} />
                    <Button variant="primary" type="submit">{t.whatsapp.simulate}</Button>
                </CardForm>
            ) : null}
            {conversations.data.length === 0 ? <EmptyState title={t.whatsapp.empty} description={t.whatsapp.emptyHint} /> : null}
            {conversations.data.length > 0 ? (
                <Card className="overflow-hidden">
                    {conversations.data.map((conversation) => (
                        <Link key={conversation.id} href={`/whatsapp/${conversation.id}`} className="flex items-start gap-3 border-b border-zinc-200 px-4 py-3 transition-colors last:border-b-0 hover:bg-zinc-50">
                            <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-teal-50 text-sm font-semibold text-teal-800">
                                {(conversation.customer ?? conversation.phone).trim().charAt(0).toUpperCase()}
                            </span>
                            <span className="min-w-0 flex-1">
                                <span className="flex items-center justify-between gap-3">
                                    <span className="truncate font-medium text-zinc-900">{conversation.customer ?? t.whatsapp.unknown}</span>
                                    <span className="flex shrink-0 items-center gap-2">
                                        {conversation.last_message_at ? <span className="text-xs text-zinc-500 tabular-nums">{format.sameDay(conversation.last_message_at, new Date()) ? format.time(conversation.last_message_at) : format.date(new Date(conversation.last_message_at), 'short')}</span> : null}
                                        {conversation.unread_count > 0 ? <span className="rounded-full bg-teal-800 px-2 py-0.5 text-xs font-semibold text-white tabular-nums">{conversation.unread_count}</span> : null}
                                    </span>
                                </span>
                                <span className="mt-0.5 block truncate text-sm text-zinc-600" title={conversation.preview ?? ''}>{conversation.preview}</span>
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
