import type { ReactNode } from 'react';
import { useI18n } from '@/i18n';

export function DescriptionList({ children, columns = 2, className = '' }: { children: ReactNode; columns?: 1 | 2 | 3; className?: string }) {
    const grid = columns === 1 ? '' : columns === 2 ? 'sm:grid-cols-2' : 'sm:grid-cols-2 xl:grid-cols-3';

    return <dl className={`grid gap-x-6 gap-y-4 ${grid} ${className}`.trim()}>{children}</dl>;
}

function isEmpty(value: ReactNode): boolean {
    return value === null || value === undefined || value === false || value === '';
}

/**
 * One labelled value. Empty values show "None" in a muted tone. `isolate` wraps user
 * text in <bdi> so English names keep their punctuation in the Arabic UI; `ltr` does
 * the same for phone numbers, references and emails.
 */
export function DescriptionItem({
    label,
    children,
    full = false,
    isolate = false,
    ltr = false,
    multiline = false,
}: {
    label: ReactNode;
    children?: ReactNode;
    full?: boolean;
    isolate?: boolean;
    ltr?: boolean;
    multiline?: boolean;
}) {
    const { t } = useI18n();
    const empty = isEmpty(children);

    return (
        <div className={`min-w-0 ${full ? 'sm:col-span-full' : ''}`.trim()}>
            <dt className="text-caption font-medium text-ink-muted">{label}</dt>
            <dd className={`mt-1 text-body break-words ${multiline ? 'whitespace-pre-wrap' : ''} ${empty ? 'text-ink-subtle' : 'text-ink'}`}>
                {empty ? t.common.none : ltr ? <bdi dir="ltr">{children}</bdi> : isolate ? <bdi>{children}</bdi> : children}
            </dd>
        </div>
    );
}
