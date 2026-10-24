import type { ReactNode } from 'react';
import { PageTitle, type PageParent } from '@/components/PageTitle';

export type RecordMeta = { key: string; label: string; value: ReactNode };

/**
 * Header for a record page: type eyebrow, title with status badges, a row of
 * "Label: value" facts and the page actions.
 */
export function RecordHeader({
    eyebrow,
    title,
    badges,
    meta = [],
    actions,
    parent,
}: {
    eyebrow?: ReactNode;
    title: string;
    /** Breadcrumb parent shown in the top bar and added to the tab title. */
    parent?: PageParent;
    badges?: ReactNode;
    meta?: RecordMeta[];
    actions?: ReactNode;
}) {
    return (
        <header className="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <PageTitle title={title} parent={parent} />
            <div className="min-w-0">
                {eyebrow ? <p className="text-caption font-medium text-ink-muted">{eyebrow}</p> : null}
                <div className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-2">
                    <h1 className="min-w-0 text-title font-semibold tracking-tight break-words text-ink">
                        <bdi>{title}</bdi>
                    </h1>
                    {badges ? <div className="flex flex-wrap items-center gap-1.5">{badges}</div> : null}
                </div>
                {meta.length > 0 ? (
                    <ul className="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-body">
                        {meta.map((item) => (
                            <li key={item.key} className="min-w-0 break-words">
                                <span className="text-ink-muted">{item.label}:</span> <span className="text-ink">{item.value}</span>
                            </li>
                        ))}
                    </ul>
                ) : null}
            </div>
            {actions ? <div className="flex shrink-0 flex-wrap gap-2 print:hidden">{actions}</div> : null}
        </header>
    );
}
