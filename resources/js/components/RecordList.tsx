import { Link, router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { ListState } from '@/components/ListState';
import { LoadingState } from '@/components/LoadingState';
import { useVisitState } from '@/components/VisitState';
import { useI18n } from '@/i18n';

const actionClassName = 'inline-flex min-h-9 items-center rounded-control border border-line bg-surface px-3 text-body font-medium text-ink-secondary hover:bg-surface-muted hover:text-ink';

/**
 * Wraps a list with its error, loading and empty states.
 *
 * - `filtered`: the list is empty because of filters, so offer to clear them.
 * - `loading="inline"`: the child (a DataTable) draws its own skeleton rows;
 *   the default "block" shows a loading card instead of the children.
 */
export function RecordList({
    empty,
    filtered = false,
    emptyTitle,
    emptyDescription,
    emptyAction,
    loading: loadingMode = 'block',
    children,
}: {
    empty: boolean;
    filtered?: boolean;
    emptyTitle?: string;
    emptyDescription?: string;
    emptyAction?: ReactNode;
    loading?: 'block' | 'inline';
    children: ReactNode;
}) {
    const { t } = useI18n();
    const { url } = usePage();
    const { loading, failed } = useVisitState();

    if (failed) {
        return (
            <ListState
                kind="error"
                title={t.list.errorTitle}
                description={t.list.errorHint}
                action={
                    <button type="button" className={actionClassName} onClick={() => router.reload()}>
                        {t.list.retry}
                    </button>
                }
            />
        );
    }

    if (loading && loadingMode === 'block') {
        return <LoadingState label={t.common.loading} />;
    }

    if (empty && !loading) {
        return filtered ? (
            <ListState
                kind="filtered"
                title={t.ops.empty}
                action={
                    <Link href={url.split('?')[0] ?? '/'} className={actionClassName}>
                        {t.list.clearFilters}
                    </Link>
                }
            />
        ) : (
            <ListState kind="empty" title={emptyTitle ?? t.list.emptyTitle} description={emptyDescription ?? t.common.emptyHint} action={emptyAction} />
        );
    }

    return children;
}
