import { Badge, type BadgeTone } from '@/components/Badge';
import { useI18n } from '@/i18n';
import { optionLabel } from '@/lib/labels';

const tones: Record<string, BadgeTone> = {
    active: 'success',
    available: 'success',
    completed: 'success',
    confirmed: 'success',
    accepted: 'success',
    paid: 'success',
    qualified: 'info',
    scheduled: 'info',
    assigned: 'info',
    in_progress: 'info',
    on_the_way: 'info',
    arrived: 'info',
    new: 'info',
    busy: 'warning',
    todo: 'warning',
    draft: 'warning',
    issued: 'warning',
    partially_paid: 'warning',
    human_required: 'warning',
    urgent: 'danger',
    overdue: 'danger',
    cancelled: 'danger',
    no_show: 'danger',
    inactive: 'muted',
    unavailable: 'muted',
    off_duty: 'muted',
};

export function StatusBadge({ value, label }: { value: string; label?: string }) {
    const { t } = useI18n();

    return <Badge tone={tones[value] ?? 'neutral'}>{label ?? optionLabel(t.labels, value)}</Badge>;
}
