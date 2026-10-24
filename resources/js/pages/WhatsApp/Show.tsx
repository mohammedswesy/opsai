import { useForm } from '@inertiajs/react';
import { MessageCircle, SendHorizontal } from 'lucide-react';
import { Fragment, useEffect, useRef, type KeyboardEvent as ReactKeyboardEvent, type ReactNode } from 'react';
import { Button } from '@/components/Button';
import { PageHeader } from '@/components/PageHeader';
import { StatusBadge } from '@/components/StatusBadge';
import { useI18n } from '@/i18n';
import AppLayout from '@/layouts/AppLayout';
import { useFormat } from '@/lib/format';
import { optionLabel } from '@/lib/labels';

type MessageRow = {
    id: string;
    direction: string;
    author: string;
    text: string | null;
    status: string;
    created_at: string | null;
};

const fieldClassName = 'min-h-11 w-full rounded-control border border-line bg-surface px-3 text-body text-ink placeholder:text-ink-subtle';

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
    const threadRef = useRef<HTMLDivElement>(null);
    const authors: Record<string, string> = { ai: t.whatsapp.authorAi, staff: t.whatsapp.authorStaff, system: t.whatsapp.authorSystem };

    // Open at, and stay on, the newest message.
    useEffect(() => {
        const element = threadRef.current;

        if (element) {
            element.scrollTop = element.scrollHeight;
        }
    }, [messages.length]);

    function sendReply() {
        if (reply.data.message.trim() === '' || reply.processing) {
            return;
        }

        reply.post(`/whatsapp/${conversation.id}/reply`, { preserveScroll: true, onSuccess: () => reply.reset('message') });
    }

    function onReplyKeyDown(event: ReactKeyboardEvent<HTMLTextAreaElement>) {
        if (event.key === 'Enter' && !event.shiftKey && !event.nativeEvent.isComposing) {
            event.preventDefault();
            sendReply();
        }
    }

    return (
        <>
            <PageHeader
                parent={{ label: t.nav.whatsapp, href: '/whatsapp' }}
                title={conversation.customer ?? t.whatsapp.unknown}
                subtitle={<bdi dir="ltr" className="tabular-nums">{conversation.phone}</bdi>}
                action={
                    <span className="self-start sm:self-auto">
                        <StatusBadge value={conversation.status} />
                    </span>
                }
            />
            {conversation.status === 'human_required' ? (
                <div className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-card border border-warning-border bg-warning-soft px-4 py-3 text-body text-warning" role="status">
                    <p className="font-medium">{t.whatsapp.human}</p>
                    {canReply ? (
                        <Button variant="secondary" type="button" onClick={() => reply.post(`/whatsapp/${conversation.id}/resume`)}>
                            {t.whatsapp.resume}
                        </Button>
                    ) : null}
                </div>
            ) : null}

            <section className="flex h-[calc(100dvh-15rem)] min-h-[26rem] flex-col rounded-card border border-line bg-surface shadow-card">
                <div ref={threadRef} role="log" aria-live="polite" aria-relevant="additions text" aria-label={t.whatsapp.title} className="min-h-0 flex-1 space-y-3 overflow-y-auto bg-surface-muted px-4 py-4 sm:px-5">
                    {messages.length === 0 ? (
                        <div className="flex h-full flex-col items-center justify-center text-center">
                            <span className="flex size-11 items-center justify-center rounded-badge bg-surface text-ink-subtle">
                                <MessageCircle className="size-5" strokeWidth={1.75} aria-hidden="true" />
                            </span>
                            <p className="mt-3 text-body font-medium text-ink">{t.whatsapp.empty}</p>
                            <p className="mt-1 max-w-sm text-body text-ink-muted">{t.whatsapp.emptyHint}</p>
                        </div>
                    ) : null}
                    {messages.map((message, index) => {
                        const outbound = message.direction !== 'inbound';
                        const previous = messages[index - 1];
                        const newDay = message.created_at !== null && (!previous?.created_at || !format.sameDay(previous.created_at, message.created_at));

                        return (
                            <Fragment key={message.id}>
                                {newDay && message.created_at ? (
                                    <p className="flex items-center gap-3 py-1 text-caption text-ink-muted before:h-px before:flex-1 before:bg-line after:h-px after:flex-1 after:bg-line">
                                        <span>{format.date(new Date(message.created_at), 'long')}</span>
                                    </p>
                                ) : null}
                                <article
                                    data-testid="whatsapp-message"
                                    data-author={message.author}
                                    className={`max-w-[85%] px-4 py-2.5 sm:max-w-xl ${
                                        outbound ? 'ms-auto rounded-card rounded-ee-control bg-brand text-on-brand' : 'me-auto rounded-card rounded-es-control border border-line bg-surface text-ink shadow-control'
                                    }`}
                                >
                                    <p className={`text-caption font-medium ${outbound ? 'text-on-brand/80' : 'text-brand'}`}>{authors[message.author] ?? t.whatsapp.authorCustomer}</p>
                                    <p dir="auto" className="mt-0.5 text-body whitespace-pre-wrap break-words">
                                        {message.text}
                                    </p>
                                    <p className={`mt-1 flex justify-end gap-1.5 text-micro tabular-nums ${outbound ? 'text-on-brand/80' : 'text-ink-muted'}`}>
                                        {message.created_at ? <time dateTime={message.created_at}>{format.time(message.created_at)}</time> : null}
                                        {message.created_at ? <span aria-hidden="true">·</span> : null}
                                        <span>{optionLabel(t.labels, message.status)}</span>
                                    </p>
                                </article>
                            </Fragment>
                        );
                    })}
                </div>

                {canReply || simulation ? (
                    <div className="space-y-2 border-t border-line p-3 sm:p-4">
                        {canReply ? (
                            <form
                                className="flex items-end gap-2"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    sendReply();
                                }}
                            >
                                <textarea
                                    rows={1}
                                    className="max-h-32 min-h-11 w-full resize-none rounded-control border border-line bg-surface px-3 py-2.5 text-body text-ink placeholder:text-ink-subtle"
                                    aria-label={t.whatsapp.reply}
                                    placeholder={t.whatsapp.reply}
                                    value={reply.data.message}
                                    onChange={(event) => reply.setData('message', event.target.value)}
                                    onKeyDown={onReplyKeyDown}
                                />
                                <Button variant="primary" type="submit" disabled={reply.processing} className="min-h-11">
                                    <SendHorizontal className="size-4 rtl:rotate-180" strokeWidth={1.75} aria-hidden="true" />
                                    {t.whatsapp.send}
                                </Button>
                            </form>
                        ) : null}
                        {simulation ? (
                            <form
                                className="flex items-center gap-2"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    inbound.post('/whatsapp/simulate', { preserveScroll: true, onSuccess: () => inbound.reset('text') });
                                }}
                            >
                                <input className={fieldClassName} aria-label={t.whatsapp.simulate} placeholder={t.whatsapp.simulate} value={inbound.data.text} onChange={(event) => inbound.setData('text', event.target.value)} />
                                <Button variant="secondary" type="submit" className="min-h-11 shrink-0">
                                    {t.whatsapp.simulate}
                                </Button>
                            </form>
                        ) : null}
                    </div>
                ) : null}
            </section>
        </>
    );
}

Show.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
