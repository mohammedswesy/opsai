import { Badge, type BadgeTone } from '@/components/Badge';
import { useI18n } from '@/i18n';
import { optionLabel } from '@/lib/labels';

const tones: Record<string, BadgeTone> = {
    low: 'quiet',
    normal: 'info',
    high: 'warning',
    urgent: 'danger',
};

export function PriorityBadge({ value }: { value: string }) {
    const { t } = useI18n();

    return (
        <Badge tone={tones[value] ?? 'neutral'} outlined={false}>
            {optionLabel(t.labels, value)}
        </Badge>
    );
}
