import { ArrowRight } from 'lucide-react';
import { useI18n } from '@/i18n';
import { useFormat } from '@/lib/format';
import { optionLabel } from '@/lib/labels';

export type ActivityEntry = {
    id: string;
    action: string;
    description?: string | null;
    created_at?: string | null;
    actor?: { name: string } | null;
    properties?: Record<string, unknown> | null;
};

/**
 * Vertical activity feed: who did what, the from → to change when the backend logged
 * one, and when. Status keys in descriptions and changes are translated.
 */
export function ActivityTimeline({ items }: { items: ActivityEntry[] }) {
    const { t } = useI18n();
    const format = useFormat();
    const labels = t.labels as Record<string, string>;
    const value = (raw: unknown): string => (typeof raw === 'string' ? (labels[raw] ?? raw) : String(raw ?? ''));

    if (items.length === 0) {
        return <p className="text-body text-ink-muted">{t.ops.noActivity}</p>;
    }

    return (
        <ol className="space-y-4">
            {items.map((item, index) => {
                const from = item.properties?.from;
                const to = item.properties?.to;
                const changed = typeof to === 'string' && to !== '';

                return (
                    <li key={item.id} className="relative ps-6">
                        {index < items.length - 1 ? <span aria-hidden="true" className="absolute start-1 top-4 -bottom-4 w-0.5 rounded-badge bg-line" /> : null}
                        <span aria-hidden="true" className="absolute start-0 top-1.5 size-2.5 rounded-badge border-2 border-brand bg-surface" />
                        <p className="text-body text-ink">
                            <bdi className="font-medium">{item.actor?.name ?? t.detail.system}</bdi> <span className="text-ink-secondary">{optionLabel(t.labels, item.action)}</span>
                        </p>
                        {changed ? (
                            <p className="mt-0.5 flex flex-wrap items-center gap-1.5 text-caption text-ink-secondary">
                                {typeof from === 'string' && from !== '' ? (
                                    <>
                                        <span>{value(from)}</span>
                                        <ArrowRight aria-hidden="true" className="size-3 shrink-0 rtl:rotate-180" strokeWidth={1.75} />
                                    </>
                                ) : null}
                                <span className="font-medium text-ink">{value(to)}</span>
                            </p>
                        ) : item.description ? (
                            <p className="mt-0.5 text-caption break-words text-ink-secondary">
                                <bdi>{value(item.description)}</bdi>
                            </p>
                        ) : null}
                        {item.created_at ? (
                            <time dateTime={item.created_at} className="mt-0.5 block text-caption text-ink-muted tabular-nums">
                                {format.dateTime(item.created_at)}
                            </time>
                        ) : null}
                    </li>
                );
            })}
        </ol>
    );
}
