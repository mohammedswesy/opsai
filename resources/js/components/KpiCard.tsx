import type { LucideIcon } from 'lucide-react';

const tones = {
    default: { icon: 'bg-brand-soft text-brand', accent: 'before:bg-brand' },
    success: { icon: 'bg-success-soft text-success', accent: 'before:bg-success' },
    warning: { icon: 'bg-warning-soft text-warning', accent: 'before:bg-warning' },
    danger: { icon: 'bg-danger-soft text-danger', accent: 'before:bg-danger' },
    info: { icon: 'bg-info-soft text-info', accent: 'before:bg-info' },
};

/**
 * Metric card. `primary` cards carry a tone accent on their start edge and a large
 * figure; `quiet` cards are compact secondary stats. Cards are not interactive, so
 * they have no hover effect. The label is the first <p> and the value the second
 * (the e2e helpers read them in that order).
 */
export function KpiCard({
    label,
    value,
    icon: Icon,
    tone = 'default',
    size = 'primary',
    hint,
}: {
    label: string;
    value: number | string;
    icon?: LucideIcon;
    tone?: keyof typeof tones;
    size?: 'primary' | 'quiet';
    hint?: string;
}) {
    const quiet = size === 'quiet';

    return (
        <article
            className={`relative overflow-hidden rounded-card border border-line bg-surface shadow-card ${quiet ? 'px-4 py-3' : `px-5 py-4 before:absolute before:inset-y-0 before:start-0 before:w-1 ${tones[tone].accent}`}`}
        >
            <div className="flex items-start justify-between gap-3">
                <p className={`font-medium text-ink-secondary ${quiet ? 'text-caption' : 'text-body'}`}>{label}</p>
                {Icon ? (
                    <span className={`flex shrink-0 items-center justify-center rounded-control ${quiet ? 'size-8' : 'size-10'} ${tones[tone].icon}`}>
                        <Icon className={quiet ? 'size-4' : 'size-5'} strokeWidth={1.75} aria-hidden="true" />
                    </span>
                ) : null}
            </div>
            <p className={`font-semibold tracking-tight text-ink tabular-nums ${quiet ? 'mt-1 text-heading' : 'mt-3 text-display'}`}>{value}</p>
            {hint ? <p className="mt-1 text-caption text-ink-muted">{hint}</p> : null}
        </article>
    );
}
