import { Card } from '@/components/Card';
import { Button } from '@/components/Button';
import { useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { EmptyState } from '@/components/EmptyState';
import { PageHeader } from '@/components/PageHeader';
import { StatusBadge } from '@/components/StatusBadge';
import { useI18n } from '@/i18n';
import { optionLabel } from '@/lib/labels';
import AppLayout from '@/layouts/AppLayout';
import { useFormat } from '@/lib/format';

type MessageRow = {
    id: string;
    direction: string;
    author: string;
    text: string | null;
    status: string;
    created_at: string | null;
};

export default function Show({
    conversation,
    messages,
    canReply,
    simulation,
}: {
    conversation: { id: string; customer: string | null; phone: string; status: string };
    messages: MessageRow[];
    canReply: boolean;
    simulation: boolean;
}) {
    const { t } = useI18n();
    const format = useFormat();
    const reply = useForm({ message: '' });
    const inbound = useForm({ from: conversation.phone, text: '' });

    return (
        <>
            <PageHeader parent={{ label: t.nav.whatsapp, href: '/whatsapp' }} title={conversation.customer ?? t.whatsapp.unknown} subtitle={conversation.phone} action={<StatusBadge value={conversation.status} />} />
            {conversation.status === 'human_required' ? (
                <div className="mb-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 shadow-sm" role="status">
                    <p className="font-medium">{t.whatsapp.human}</p>
                </div>
            ) : null}
            {messages.length === 0 ? <EmptyState title={t.whatsapp.empty} description={t.whatsapp.emptyHint} /> : null}
            <Card className="space-y-3 p-4 shadow-sm sm:p-5">
                {messages.map((message) => {
                    const outbound = message.direction !== 'inbound';
                    const author = message.author === 'ai' ? t.whatsapp.authorAi : message.author === 'staff' ? t.whatsapp.authorStaff : message.author === 'system' ? t.whatsapp.authorSystem : t.whatsapp.authorCustomer;

                    return (
                        <article
                            key={message.id}
                            data-testid="whatsapp-message"
                            data-author={message.author}
                            className={outbound ? 'ms-auto max-w-[36rem] rounded-2xl rounded-ee-md bg-teal-800 px-4 py-3 text-white shadow-sm' : 'me-auto max-w-[36rem] rounded-2xl rounded-es-md border border-zinc-200 bg-white px-4 py-3 shadow-sm'}
                        >
                            <p className={`text-xs font-medium ${outbound ? 'text-teal-100' : 'text-zinc-500'}`}>{author}</p>
                            <p className={`mt-1 whitespace-pre-wrap break-words text-sm ${outbound ? 'text-white' : 'text-zinc-900'}`}>{message.text}</p>
                            <p className={`mt-1 text-xs ${outbound ? 'text-teal-100' : 'text-zinc-400'}`}>{message.created_at ? <span className="tabular-nums">{format.time(message.created_at)} · </span> : null}{optionLabel(t.labels, message.status)}</p>
                        </article>
                    );
                })}
            </Card>
            {canReply ? (
                <form
                    className="mt-6 flex gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        reply.post(`/whatsapp/${conversation.id}/reply`);
                    }}
                >
                    <input className="min-h-11 flex-1 rounded-lg border border-zinc-200 px-3 text-sm" aria-label={t.whatsapp.reply} value={reply.data.message} onChange={(event) => reply.setData('message', event.target.value)} />
                    <Button variant="primary" type="submit">{t.whatsapp.send}</Button>
                </form>
            ) : null}
            {conversation.status === 'human_required' && canReply ? (
                <Button variant="secondary" className="mt-3" type="button" onClick={() => reply.post(`/whatsapp/${conversation.id}/resume`)}>{t.whatsapp.resume}</Button>
            ) : null}
            {simulation ? (
                <form
                    className="mt-6 flex gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        inbound.post('/whatsapp/simulate');
                    }}
                >
                    <input className="min-h-11 flex-1 rounded-lg border border-zinc-200 px-3 text-sm" aria-label={t.whatsapp.simulate} value={inbound.data.text} onChange={(event) => inbound.setData('text', event.target.value)} />
                    <Button variant="secondary" type="submit">{t.whatsapp.simulate}</Button>
                </form>
            ) : null}
        </>
    );
}

Show.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
