import { optionLabel } from '@/lib/labels';
import { useI18n } from '@/i18n';

const tones: Record<string, string> = {
    active: 'bg-green-50 text-green-700 ring-green-200',
    available: 'bg-green-50 text-green-700 ring-green-200',
    completed: 'bg-green-50 text-green-700 ring-green-200',
    confirmed: 'bg-green-50 text-green-700 ring-green-200',
    accepted: 'bg-green-50 text-green-700 ring-green-200',
    paid: 'bg-green-50 text-green-700 ring-green-200',
    qualified: 'bg-sky-50 text-sky-700 ring-sky-200',
    scheduled: 'bg-sky-50 text-sky-700 ring-sky-200',
    assigned: 'bg-sky-50 text-sky-700 ring-sky-200',
    in_progress: 'bg-sky-50 text-sky-700 ring-sky-200',
    on_the_way: 'bg-sky-50 text-sky-700 ring-sky-200',
    arrived: 'bg-sky-50 text-sky-700 ring-sky-200',
    new: 'bg-sky-50 text-sky-700 ring-sky-200',
    busy: 'bg-amber-50 text-amber-700 ring-amber-200',
    todo: 'bg-amber-50 text-amber-700 ring-amber-200',
    draft: 'bg-amber-50 text-amber-700 ring-amber-200',
    issued: 'bg-amber-50 text-amber-700 ring-amber-200',
    partially_paid: 'bg-amber-50 text-amber-700 ring-amber-200',
    human_required: 'bg-amber-50 text-amber-700 ring-amber-200',
    urgent: 'bg-red-50 text-red-700 ring-red-200',
    overdue: 'bg-red-50 text-red-700 ring-red-200',
    cancelled: 'bg-red-50 text-red-700 ring-red-200',
    no_show: 'bg-red-50 text-red-700 ring-red-200',
    inactive: 'bg-zinc-50 text-zinc-500 ring-zinc-200',
    unavailable: 'bg-zinc-50 text-zinc-500 ring-zinc-200',
    off_duty: 'bg-zinc-50 text-zinc-500 ring-zinc-200',
};

export function StatusBadge({ value, label }: { value: string; label?: string }) {
    const { t } = useI18n();

    return (
        <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ${tones[value] ?? 'bg-zinc-50 text-zinc-700 ring-zinc-200'}`}>
            {label ?? optionLabel(t.labels, value)}
        </span>
    );
}
