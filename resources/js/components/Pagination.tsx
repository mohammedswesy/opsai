import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useI18n } from '@/i18n';
import { useFormat } from '@/lib/format';
import { fill } from '@/lib/text';
import type { Paginator } from '@/types/page';

const stepClassName = 'inline-flex min-h-9 items-center gap-1 rounded-control border border-line bg-surface px-3 text-body font-medium text-ink-secondary';

/**
 * Previous/next and page links for a Laravel paginator. Labels come from our own
 * translations (not Laravel's « Previous / Next » HTML) and the chevrons flip in RTL.
 */
export function Pagination({ paginator }: { paginator: Paginator<unknown> }) {
    const { t } = useI18n();
    const format = useFormat();
    const links = paginator.links;

    if (links.length <= 3) {
        return null;
    }

    const previous = links[0];
    const next = links[links.length - 1];
    const pages = links.slice(1, -1);
    const current = paginator.current_page ?? Number(pages.find((link) => link.active)?.label ?? 1);
    const last = paginator.last_page ?? pages.length;

    return (
        <nav aria-label={t.list.pagination} className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-caption text-ink-muted tabular-nums">
                {paginator.from && paginator.to && paginator.total
                    ? fill(t.list.showing, { from: format.number(paginator.from), to: format.number(paginator.to), total: format.number(paginator.total) })
                    : fill(t.list.pageOf, { page: format.number(current), last: format.number(last) })}
            </p>
            <div className="flex items-center gap-1.5">
                {previous?.url ? (
                    <Link href={previous.url} className={`${stepClassName} hover:bg-surface-muted hover:text-ink`} rel="prev" preserveState>
                        <ChevronLeft className="size-4 rtl:rotate-180" strokeWidth={1.75} aria-hidden="true" />
                        {t.list.previous}
                    </Link>
                ) : (
                    <span className={`${stepClassName} text-ink-subtle`} aria-disabled="true">
                        <ChevronLeft className="size-4 rtl:rotate-180" strokeWidth={1.75} aria-hidden="true" />
                        {t.list.previous}
                    </span>
                )}
                <span className="px-2 text-caption text-ink-muted tabular-nums sm:hidden">{fill(t.list.pageOf, { page: format.number(current), last: format.number(last) })}</span>
                <ul className="hidden items-center gap-1 sm:flex">
                    {pages.map((link, index) => (
                        <li key={`${link.label}-${index}`}>
                            {link.url && !link.active ? (
                                <Link href={link.url} className="inline-flex min-h-9 min-w-9 items-center justify-center rounded-control px-2 text-body text-ink-secondary tabular-nums hover:bg-surface-muted hover:text-ink" preserveState>
                                    {/^\d+$/.test(link.label) ? format.number(link.label) : link.label}
                                </Link>
                            ) : (
                                <span
                                    aria-current={link.active ? 'page' : undefined}
                                    className={`inline-flex min-h-9 min-w-9 items-center justify-center rounded-control px-2 text-body tabular-nums ${link.active ? 'bg-brand font-semibold text-on-brand' : 'text-ink-subtle'}`}
                                >
                                    {/^\d+$/.test(link.label) ? format.number(link.label) : '…'}
                                </span>
                            )}
                        </li>
                    ))}
                </ul>
                {next?.url ? (
                    <Link href={next.url} className={`${stepClassName} hover:bg-surface-muted hover:text-ink`} rel="next" preserveState>
                        {t.list.next}
                        <ChevronRight className="size-4 rtl:rotate-180" strokeWidth={1.75} aria-hidden="true" />
                    </Link>
                ) : (
                    <span className={`${stepClassName} text-ink-subtle`} aria-disabled="true">
                        {t.list.next}
                        <ChevronRight className="size-4 rtl:rotate-180" strokeWidth={1.75} aria-hidden="true" />
                    </span>
                )}
            </div>
        </nav>
    );
}
