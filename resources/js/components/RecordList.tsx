import type { ReactNode } from 'react';
import { EmptyState } from '@/components/EmptyState';
import { LoadingState } from '@/components/LoadingState';
import { useVisitState } from '@/components/VisitState';
import { useI18n } from '@/i18n';

export function RecordList({ empty, children }: { empty: boolean; children: ReactNode }) {
    const { t } = useI18n();
    const { loading, failed } = useVisitState();

    if (failed) {
        return <EmptyState title={t.common.error} />;
    }

    if (loading) {
        return <LoadingState label={t.common.loading} />;
    }

    if (empty) {
        return <EmptyState title={t.ops.empty} />;
    }

    return children;
}
