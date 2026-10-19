import { optionLabel } from '@/lib/labels';
import { useI18n } from '@/i18n';

const tones: Record<string, string> = {
    low: 'bg-zinc-100 text-zinc-600',
    normal: 'bg-sky-50 text-sky-700',
    high: 'bg-amber-50 text-amber-700',
    urgent: 'bg-red-50 text-red-700',
};

export function PriorityBadge({ value }: { value: string }) {
    const { t } = useI18n();

    return (
        <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${tones[value] ?? 'bg-zinc-100 text-zinc-700'}`}>
            {optionLabel(t.labels, value)}
        </span>
    );
}
