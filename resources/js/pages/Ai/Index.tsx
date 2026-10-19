import { Button } from '@/components/Button';
import { Card } from '@/components/Card';
import { Link, router } from '@inertiajs/react';
import { useRef, useState, type ReactNode } from 'react';
import { EmptyState } from '@/components/EmptyState';
import { controlClassName } from '@/components/FormField';
import { PageHeader } from '@/components/PageHeader';
import { useI18n } from '@/i18n';
import AppLayout from '@/layouts/AppLayout';
import type { Option } from '@/types/page';

type Preview = { key: string; value: string };
type ChatMessage = { id: string; role: string; content: string | null; metadata?: { tools?: string[]; tool?: string } | null };
type Pending = { id: string; tool_name: string; status: string; preview: Preview[] } | null;

export default function Index({
    conversations,
    active,
    messages,
    pendingAction,
    branches,
    branchId,
}: {
    conversations: { id: string; title: string | null }[];
    active: { id: string; title: string | null } | null;
    messages: ChatMessage[];
    pendingAction: Pending;
    branches: Option[];
    branchId: string | null;
}) {
    const { t } = useI18n();
    const [thread, setThread] = useState<ChatMessage[]>(messages);
    const [conversationId, setConversationId] = useState<string | null>(active?.id ?? null);
    const conversationRef = useRef<string | null>(active?.id ?? null);
    const [draft, setDraft] = useState('');
    const [pending, setPending] = useState<Pending>(pendingAction);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [toolsUsed, setToolsUsed] = useState<string[]>([]);
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

    return (
        <div className="grid gap-4 lg:grid-cols-[17rem_1fr]">
            <aside className="rounded-xl border border-zinc-200 bg-white p-4">
                <div className="mb-3 flex items-center justify-between gap-2">
                    <h2 className="text-sm font-semibold">{t.ai.conversations}</h2>
                    <button
                        type="button"
                        className="text-sm font-medium text-teal-800"
                        onClick={() => {
                            conversationRef.current = null;
                            setConversationId(null);
                            setThread([]);
                            setPending(null);
                            setError(null);
                            setToolsUsed([]);
                        }}
                    >
                        {t.ai.newConversation}
                    </button>
                </div>
                <ul className="space-y-1 text-sm">
                    {conversations.length === 0 ? <li className="px-2 py-3 text-xs text-zinc-400">{t.ai.empty}</li> : null}
                    {conversations.map((conversation) => (
                        <li key={conversation.id}>
                            <Link
                                href={`/ai/${conversation.id}`}
                                className={`block truncate rounded-lg px-2.5 py-2 ${conversation.id === conversationId ? 'bg-teal-50 font-medium text-teal-800' : 'text-zinc-600 hover:bg-zinc-50'}`}
                            >
                                {conversation.title || t.ai.title}
                            </Link>
                        </li>
                    ))}
                </ul>
            </aside>
            <Card className="flex min-h-[32rem] flex-col p-4 sm:p-5">
                <PageHeader title={t.ai.title} subtitle={t.ai.subtitle} />
                <label className="mb-4 block max-w-xs text-sm">
                    <span className="mb-1 block text-zinc-600">{t.ai.branch}</span>
                    <select
                        className={controlClassName}
                        value={branchId ?? ''}
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
                <div className="mb-4 flex-1 space-y-3">
                    {thread.length === 0 ? <EmptyState title={t.ai.empty} description={t.ai.emptyHint} /> : null}
                    {thread.map((message) => (
                        <article
                            key={message.id}
                            className={
                                message.role === 'user'
                                    ? 'ms-auto max-w-[40rem] rounded-2xl rounded-ee-md bg-teal-800 px-4 py-3 text-sm text-white shadow-sm'
                                    : message.role === 'tool'
                                      ? 'me-auto max-w-[40rem] rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm shadow-sm'
                                      : 'me-auto max-w-[40rem] rounded-2xl rounded-es-md border border-zinc-200 bg-white px-4 py-3 text-sm shadow-sm'
                            }
                        >
                            <p className={`mb-1 text-xs font-medium ${message.role === 'user' ? 'text-teal-100' : 'text-teal-800'}`}>
                                {message.role === 'user' ? t.ai.you : message.role === 'tool' ? t.ai.toolResult : t.ai.title}
                            </p>
                            {message.role === 'tool' ? (
                                <p className="break-words text-zinc-600">{message.metadata?.tool ?? message.content}</p>
                            ) : (
                                <p className="whitespace-pre-wrap break-words">{message.content}</p>
                            )}
                        </article>
                    ))}
                    {toolsUsed.length > 0 ? <p className="text-xs text-zinc-500">{t.ai.checked}: {toolsUsed.join(', ')}</p> : null}
                    {loading ? <p className="text-sm text-zinc-500">{t.ai.thinking}</p> : null}
                    {error ? (
                        <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 shadow-sm" role="alert">
                            <p className="break-words">{error}</p>
                            <Button variant="secondary" className="mt-3" type="button" onClick={() => send(thread.filter((message) => message.role === 'user').at(-1)?.content ?? '')}>
                                {t.ai.retry}
                            </Button>
                        </div>
                    ) : null}
                    {pending ? (
                        <section className="max-w-md rounded-xl border-2 border-teal-700 bg-teal-50 p-4 shadow-sm" data-testid="ai-confirmation">
                            <h3 className="mb-3 font-medium">{pending.tool_name}</h3>
                            <dl className="space-y-2 text-sm">
                                {pending.preview.filter((row) => row.value !== '').map((row) => (
                                    <div key={row.key}>
                                        <dt className="text-zinc-500">{fields[row.key] ?? row.key}</dt>
                                        <dd>{row.value}</dd>
                                    </div>
                                ))}
                            </dl>
                            <div className="mt-4 flex gap-2">
                                <Button variant="secondary" type="button" onClick={() => decide(`/api/ai/actions/${pending.id}/reject`)}>
                                    {t.ai.cancel}
                                </Button>
                                <Button variant="primary" type="button" onClick={() => decide(`/api/ai/actions/${pending.id}/confirm`)}>
                                    {t.ai.confirm}
                                </Button>
                            </div>
                        </section>
                    ) : null}
                </div>
                <form
                    className="flex gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        void send(draft);
                    }}
                >
                    <input
                        className={controlClassName}
                        value={draft}
                        placeholder={t.ai.placeholder}
                        aria-label={t.ai.placeholder}
                        onChange={(event) => setDraft(event.target.value)}
                    />
                    <Button variant="primary" type="submit" disabled={loading}>
                        {t.ai.send}
                    </Button>
                </form>
            </Card>
        </div>
    );
}

Index.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
