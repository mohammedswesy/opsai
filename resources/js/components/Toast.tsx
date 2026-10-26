import { CircleAlert, CircleCheck, Info, TriangleAlert, X } from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';

export type AlertTone = 'success' | 'info' | 'warning' | 'danger';

const tones: Record<AlertTone, { box: string; dismiss: string; icon: typeof Info }> = {
    success: { box: 'border-success-border bg-success-soft text-success', dismiss: 'hover:bg-success-border', icon: CircleCheck },
    info: { box: 'border-info-border bg-info-soft text-info', dismiss: 'hover:bg-info-border', icon: Info },
    warning: { box: 'border-warning-border bg-warning-soft text-warning', dismiss: 'hover:bg-warning-border', icon: TriangleAlert },
    danger: { box: 'border-danger-border bg-danger-soft text-danger', dismiss: 'hover:bg-danger-border', icon: CircleAlert },
};

/**
 * Inline message with a tone icon, so the tone is not carried by colour alone. Errors
 * and warnings use `role="alert"` (announced at once), the rest `role="status"`.
 * `autoDismissMs` closes it after a delay, paused while it is hovered or focused; leave
 * it off for errors so people have time to read them.
 */
export function Alert({
    tone = 'info',
    onDismiss,
    dismissLabel,
    autoDismissMs,
    className = '',
    children,
}: {
    tone?: AlertTone;
    onDismiss?: () => void;
    dismissLabel?: string;
    autoDismissMs?: number;
    className?: string;
    children: ReactNode;
}) {
    const [paused, setPaused] = useState(false);
    const style = tones[tone];
    const Icon = style.icon;

    useEffect(() => {
        if (!autoDismissMs || !onDismiss || paused) {
            return;
        }

        const timer = window.setTimeout(onDismiss, autoDismissMs);

        return () => window.clearTimeout(timer);
    }, [autoDismissMs, onDismiss, paused]);

    return (
        <div
            className={`flex items-start gap-2 rounded-card border px-4 py-3 text-body shadow-control ${style.box} ${className}`.trim()}
            role={tone === 'danger' || tone === 'warning' ? 'alert' : 'status'}
            onMouseEnter={() => setPaused(true)}
            onMouseLeave={() => setPaused(false)}
            onFocus={() => setPaused(true)}
            onBlur={() => setPaused(false)}
        >
            <Icon className="mt-0.5 size-4 shrink-0" strokeWidth={1.75} aria-hidden="true" />
            <div className="min-w-0 flex-1 break-words">{children}</div>
            {onDismiss && dismissLabel ? (
                <button type="button" className={`-m-1 shrink-0 rounded-control p-1 ${style.dismiss}`} aria-label={dismissLabel} onClick={onDismiss}>
                    <X className="size-4" strokeWidth={1.75} aria-hidden="true" />
                </button>
            ) : null}
        </div>
    );
}
