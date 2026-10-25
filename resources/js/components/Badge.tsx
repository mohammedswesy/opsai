import type { ReactNode } from 'react';

const tones = {
    neutral: 'bg-surface-muted text-ink-body ring-line',
    muted: 'bg-surface-muted text-ink-muted ring-line',
    quiet: 'bg-surface-muted text-ink-secondary ring-line',
    brand: 'bg-brand-soft text-brand ring-brand-border',
    info: 'bg-info-soft text-info ring-info-border',
    success: 'bg-success-soft text-success ring-success-border',
    warning: 'bg-warning-soft text-warning ring-warning-border',
    danger: 'bg-danger-soft text-danger ring-danger-border',
};

export type BadgeTone = keyof typeof tones;

/**
 * Small rounded label on the semantic tones. `outlined` adds the tone's ring; status
 * badges use it, priority badges do not, so the two read differently side by side.
 */
export function Badge({ tone = 'neutral', outlined = true, children }: { tone?: BadgeTone; outlined?: boolean; children: ReactNode }) {
    return <span className={`inline-flex items-center rounded-badge px-2 py-0.5 text-caption font-medium ${outlined ? 'ring-1' : ''} ${tones[tone]}`}>{children}</span>;
}
