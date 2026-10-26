import { Link, router } from '@inertiajs/react';
import { History, Plus, SendHorizontal, ShieldCheck, Sparkles } from 'lucide-react';
import { useEffect, useRef, useState, type KeyboardEvent as ReactKeyboardEvent, type ReactNode } from 'react';
import { Button } from '@/components/Button';
import { controlClassName, controlFocusClassName } from '@/components/FormField';
import { PageTitle } from '@/components/PageTitle';
import { Sheet } from '@/components/Sheet';
import { useI18n } from '@/i18n';
import AppLayout from '@/layouts/AppLayout';
import type { Option } from '@/types/page';

type Preview = { key: string; value: string };
type ChatMessage = { id: string; role: string; content: string | null; metadata?: { tools?: string[]; tool?: string } | null };
type Pending = { id: string; tool_name: string; status: string; preview: Preview[] } | null;
type Conversation = { id: string; title: string | null };

const surface = 'rounded-card border border-line bg-surface shadow-card';

export default function Index({
    conversations,
    active,
    messages,
    pendingAction,
    branches,
    branchId,
}: {
    conversations: Conversation[];
    active: Conversation | null;
    messages: ChatMessage[];
    pendingAction: Pending;
    branches: Option[];
    branchId: string | null;
}) {
    const { t, locale } = useI18n();
    const toolLabel = (name: string): string => (t.ai.tools as Record<string, string>)[name] ?? name;
    // Preview values arrive as raw enum keys for some fields (e.g. priority "normal").
    const previewValue = (key: string, value: string): string => (['priority', 'status'].includes(key) ? ((t.labels as Record<string, string>)[value] ?? value) : value);
    const [thread, setThread] = useState<ChatMessage[]>(messages);
    const [conversationId, setConversationId] = useState<string | null>(active?.id ?? null);
    const conversationRef = useRef<string | null>(active?.id ?? null);
    const [draft, setDraft] = useState('');
    const [pending, setPending] = useState<Pending>(pendingAction);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [toolsUsed, setToolsUsed] = useState<string[]>([]);
    const [sheetOpen, setSheetOpen] = useState(false);
    const threadRef = useRef<HTMLDivElement>(null);
    const composerRef = useRef<HTMLTextAreaElement>(null);
    const fields: Record<string, string> = {
        title: t.ai.titleField,
        assignee: t.ai.assignee,
        priority: t.ai.priority,
        due: t.ai.due,
        customer: t.ai.customer,
        subject: t.ai.subject,
        technician: t.ai.technician,
        start: t.ai.start,
        end: t.ai.end,
        work_order: t.ai.workOrder,
    };

    // Keep the newest message in view.
    useEffect(() => {
        const element = threadRef.current;

        if (element) {
            const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            element.scrollTo({ top: element.scrollHeight, behavior: reduce ? 'auto' : 'smooth' });
        }
    }, [thread.length, pending, loading, error]);

    function resizeComposer() {
        const element = composerRef.current;

        if (element) {
            element.style.height = 'auto';
            element.style.height = `${Math.min(element.scrollHeight, 160)}px`;
        }
    }

    useEffect(resizeComposer, [draft]);

    function startConversation() {
        conversationRef.current = null;
        setConversationId(null);
        setThread([]);
        setPending(null);
        setError(null);
        setToolsUsed([]);
        setSheetOpen(false);
        composerRef.current?.focus();
    }

    async function send(text: string) {
        const message = text.trim();
        if (message === '' || loading) {
            return;
        }

        setError(null);
        setLoading(true);
        setDraft('');
        setThread((current) => [...current, { id: `local-${Date.now()}`, role: 'user', content: message }]);

        try {
            const response = await window.axios.post('/api/ai/chat', {
                message,
                conversation_id: conversationRef.current,
                branch_id: branchId ?? '',
            });
            conversationRef.current = response.data.conversation_id;
            setConversationId(response.data.conversation_id);
            setThread((current) => [...current, response.data.message]);
            setPending(response.data.pending_action);
            setToolsUsed(response.data.tools_used ?? []);
        } catch (caught) {
            const response = (caught as { response?: { data?: { message?: string } } }).response;
            setError(response?.data?.message ?? t.common.error);
        } finally {
            setLoading(false);
        }
    }

    async function decide(path: string) {
        if (!pending) {
            return;
        }

        setLoading(true);
        setError(null);

        try {
            const response = await window.axios.post(path);
            setPending(null);
            if (response.data.result?.success) {
                const saved = response.data.result.data?.title ?? response.data.result.data?.subject ?? t.common.saved;
                setThread((current) => [...current, { id: `done-${pending.id}`, role: 'assistant', content: saved }]);
            }
        } catch (caught) {
            const response = (caught as { response?: { data?: { message?: string } } }).response;
            setError(response?.data?.message ?? t.common.error);
        } finally {
            setLoading(false);
        }
    }

    function onComposerKeyDown(event: ReactKeyboardEvent<HTMLTextAreaElement>) {
        // Enter sends; Shift+Enter adds a line. Never send while an IME (e.g. Arabic) is composing.
        if (event.key === 'Enter' && !event.shiftKey && !event.nativeEvent.isComposing) {
            event.preventDefault();
            void send(draft);
        }
    }

    const conversationList = (
        <div className="space-y-3">
            <button
                type="button"
                className="inline-flex min-h-9 w-full items-center justify-center gap-1.5 rounded-control border border-line bg-surface px-3 text-body font-medium text-ink-secondary hover:bg-surface-muted hover:text-ink"
                onClick={startConversation}
            >
                <Plus className="size-4" strokeWidth={1.75} aria-hidden="true" />
                {t.ai.newConversation}
            </button>
            {conversations.length === 0 ? (
                <p className="px-1 py-2 text-caption text-ink-muted">{t.ai.empty}</p>
            ) : (
                <ul className="space-y-0.5">
                    {conversations.map((conversation) => {
                        const current = conversation.id === conversationId;

                        return (
                            <li key={conversation.id}>
                                <Link
                                    href={`/ai/${conversation.id}`}
                                    aria-current={current ? 'page' : undefined}
                                    className={`block truncate rounded-control px-2.5 py-2 text-body ${current ? 'bg-brand-soft font-medium text-brand-strong' : 'text-ink-secondary hover:bg-surface-muted hover:text-ink'}`}
                                >
                                    <bdi>{conversation.title || t.ai.title}</bdi>
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            )}
        </div>
    );

    return (
        <div className="grid gap-4 lg:grid-cols-[17rem_minmax(0,1fr)]">
            <PageTitle title={t.ai.title} />
            <Sheet open={sheetOpen} onClose={() => setSheetOpen(false)} title={t.ai.conversations} closeLabel={t.ai.close}>
                {conversationList}
            </Sheet>

            <section className={`${surface} flex h-[calc(100dvh-7.5rem)] min-h-[28rem] flex-col`}>
                <header className="flex flex-wrap items-center gap-3 border-b border-line px-4 py-3 sm:px-5">
                    <button
                        type="button"
                        className="inline-flex size-9 shrink-0 items-center justify-center rounded-control border border-line text-ink-secondary hover:bg-surface-muted lg:hidden"
                        aria-label={t.ai.conversations}
                        aria-haspopup="dialog"
                        onClick={() => setSheetOpen(true)}
                    >
                        <History className="size-4" strokeWidth={1.75} aria-hidden="true" />
                    </button>
                    <div className="min-w-0 flex-1">
                        <h1 className="text-heading font-semibold text-ink">{t.ai.title}</h1>
                        <p className="truncate text-caption text-ink-muted">{active?.title ? <bdi>{active.title}</bdi> : t.ai.subtitle}</p>
                    </div>
                    <label className="flex min-w-0 items-center gap-2 text-caption text-ink-muted">
                        <span className="sr-only sm:not-sr-only">{t.ai.branch}</span>
                        <select
                            className={`${controlClassName} w-44`}
                            value={branchId ?? ''}
                            aria-label={t.ai.branch}
                            onChange={(event) => router.get('/ai', { branch_id: event.target.value }, { preserveState: false })}
                        >
                            <option value="">{t.ai.allBranches}</option>
                            {branches.map((branch) => (
                                <option key={branch.id} value={branch.id}>
                                    {branch.name}
                                </option>
                            ))}
                        </select>
                    </label>
                </header>

                <div ref={threadRef} role="log" aria-live="polite" aria-relevant="additions text" aria-label={t.ai.title} className="min-h-0 flex-1 space-y-3 overflow-y-auto px-4 py-4 sm:px-5">
                    {thread.length === 0 && !loading ? (
                        <div className="flex h-full flex-col items-center justify-center px-6 text-center">
                            <span className="flex size-11 items-center justify-center rounded-badge bg-brand-soft text-brand">
                                <Sparkles className="size-5" strokeWidth={1.75} aria-hidden="true" />
                            </span>
                            <p className="mt-3 text-body font-medium text-ink">{t.ai.empty}</p>
                            <p className="mt-1 max-w-sm text-body text-ink-muted">{t.ai.emptyHint}</p>
                        </div>
                    ) : null}
                    {thread.map((message) => (
                        <MessageBubble key={message.id} message={message} toolLabel={toolLabel} />
                    ))}
                    {toolsUsed.length > 0 ? (
                        <p className="text-caption text-ink-muted">
                            {t.ai.checked}: {toolsUsed.map(toolLabel).join(locale === 'ar' ? '، ' : ', ')}
                        </p>
                    ) : null}
                    {loading ? (
                        <p className="flex items-center gap-2 text-body text-ink-muted">
                            <span className="size-2 animate-pulse rounded-badge bg-brand" aria-hidden="true" />
                            {t.ai.thinking}
                        </p>
                    ) : null}
                    {error ? (
                        <div className="max-w-xl rounded-card border border-danger-border bg-danger-soft p-4 text-body text-danger" role="alert">
                            <p className="break-words">{error}</p>
                            <Button variant="secondary" className="mt-3" type="button" onClick={() => send(thread.filter((message) => message.role === 'user').at(-1)?.content ?? '')}>
                                {t.ai.retry}
                            </Button>
                        </div>
                    ) : null}
                    {pending ? (
                        <section className="max-w-xl rounded-card border border-brand-border bg-brand-soft p-4" data-testid="ai-confirmation" aria-labelledby={`confirm-${pending.id}`}>
                            <div className="flex items-start gap-3">
                                <span className="flex size-9 shrink-0 items-center justify-center rounded-control bg-surface text-brand shadow-control">
                                    <ShieldCheck className="size-5" strokeWidth={1.75} aria-hidden="true" />
                                </span>
                                <div className="min-w-0">
                                    <h3 id={`confirm-${pending.id}`} className="text-heading font-semibold text-ink">
                                        {toolLabel(pending.tool_name)}
                                    </h3>
                                    <p className="mt-0.5 text-body text-ink-secondary">{t.ai.confirmIntro}</p>
                                </div>
                            </div>
                            <dl className="mt-4 grid gap-x-4 gap-y-3 rounded-control bg-surface p-3 sm:grid-cols-2">
                                {pending.preview
                                    .filter((row) => row.value !== '')
                                    .map((row) => (
                                        <div key={row.key} className="min-w-0">
                                            <dt className="text-caption text-ink-muted">{fields[row.key] ?? row.key}</dt>
                                            <dd className="mt-0.5 text-body break-words text-ink">
                                                <bdi>{previewValue(row.key, row.value)}</bdi>
                                            </dd>
                                        </div>
                                    ))}
                            </dl>
                            <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                                <p className="text-caption text-ink-muted">{t.ai.confirmHint}</p>
                                <div className="flex gap-2">
                                    <Button variant="secondary" type="button" disabled={loading} onClick={() => decide(`/api/ai/actions/${pending.id}/reject`)}>
                                        {t.ai.cancel}
                                    </Button>
                                    <Button variant="primary" type="button" disabled={loading} onClick={() => decide(`/api/ai/actions/${pending.id}/confirm`)}>
                                        {t.ai.confirm}
                                    </Button>
                                </div>
                            </div>
                        </section>
                    ) : null}
                </div>

                <form
                    className="border-t border-line p-3 sm:p-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        void send(draft);
                    }}
                >
                    <div className="flex items-end gap-2">
                        <textarea
                            ref={composerRef}
                            rows={1}
                            className={`max-h-40 min-h-11 w-full resize-none rounded-control border border-line bg-surface px-3 py-2.5 text-body text-ink placeholder:text-ink-muted ${controlFocusClassName}`}
                            value={draft}
                            placeholder={t.ai.placeholder}
                            aria-label={t.ai.placeholder}
                            aria-describedby="ai-composer-hint"
                            onChange={(event) => setDraft(event.target.value)}
                            onKeyDown={onComposerKeyDown}
                        />
                        <Button variant="primary" type="submit" disabled={loading || draft.trim() === ''} className="min-h-11">
                            <SendHorizontal className="size-4 rtl:rotate-180" strokeWidth={1.75} aria-hidden="true" />
                            {t.ai.send}
                        </Button>
                    </div>
                    <p id="ai-composer-hint" className="mt-1.5 hidden text-micro text-ink-muted sm:block">
                        {t.ai.composerHint}
                    </p>
                </form>
            </section>
            {/* After the thread in the DOM so the page h1 comes first; shown in the first column at lg. */}
            <aside className={`${surface} hidden self-start p-4 lg:order-first lg:block`}>
                <h2 className="mb-3 text-heading font-semibold text-ink">{t.ai.conversations}</h2>
                {conversationList}
            </aside>
        </div>
    );
}

function MessageBubble({ message, toolLabel }: { message: ChatMessage; toolLabel: (name: string) => string }) {
    const { t } = useI18n();

    if (message.role === 'tool') {
        return (
            <article className="me-auto max-w-[85%] rounded-control border border-dashed border-line-strong bg-surface-muted px-3 py-2 text-caption text-ink-secondary sm:max-w-xl">
                <span className="font-medium">{t.ai.toolResult}:</span> <bdi>{message.metadata?.tool ? toolLabel(message.metadata.tool) : message.content}</bdi>
            </article>
        );
    }

    const mine = message.role === 'user';

    return (
        <article
            className={`max-w-[85%] px-4 py-3 text-body sm:max-w-xl ${mine ? 'ms-auto rounded-card rounded-ee-control bg-brand text-on-brand' : 'me-auto rounded-card rounded-es-control border border-line bg-surface text-ink shadow-control'}`}
        >
            <p className={`mb-1 text-caption font-medium ${mine ? 'text-on-brand/80' : 'text-brand'}`}>{mine ? t.ai.you : t.ai.assistant}</p>
            <p dir="auto" className="whitespace-pre-wrap break-words">
                {message.content}
            </p>
        </article>
    );
}

Index.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
