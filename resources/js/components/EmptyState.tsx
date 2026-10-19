import { Inbox } from 'lucide-react';
import { Card } from '@/components/Card';
import { useI18n } from '@/i18n';

export function EmptyState({ title, description }: { title: string; description?: string }) {
    const { t } = useI18n();

    return (
        <Card className="border-dashed px-6 py-14 text-center">
            <span className="mx-auto flex size-10 items-center justify-center rounded-full bg-zinc-50 text-zinc-400">
                <Inbox className="size-5" />
            </span>
            <p className="mt-3 text-sm font-medium text-zinc-900">{title}</p>
            <p className="mx-auto mt-1 max-w-sm text-sm text-zinc-500">{description ?? t.common.emptyHint}</p>
        </Card>
    );
}
