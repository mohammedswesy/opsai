import { Inbox } from 'lucide-react';
import { Card } from '@/components/Card';
import { useI18n } from '@/i18n';

export function EmptyState({ title, description }: { title: string; description?: string }) {
    const { t } = useI18n();

    return (
        <Card className="border-dashed px-6 py-14 text-center">
            <span className="mx-auto flex size-10 items-center justify-center rounded-badge bg-surface-muted text-ink-subtle">
                <Inbox className="size-5" />
            </span>
            <p className="mt-3 text-body font-medium text-ink">{title}</p>
            <p className="mx-auto mt-1 max-w-sm text-body text-ink-muted">{description ?? t.common.emptyHint}</p>
        </Card>
    );
}
